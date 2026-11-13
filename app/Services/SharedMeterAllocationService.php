<?php

namespace App\Services;

use App\Models\BillingCycle;
use App\Models\MeterReading;
use App\Models\Occupancy;
use App\Models\Unit;
use App\Models\User;
use App\Models\UtilityAllocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SharedMeterAllocationService
{
    /** @param array<int, numeric-string|int|float> $customWeights */
    public function allocate(BillingCycle $cycle, MeterReading $mainReading, string $policy, array $customWeights, User $actor): int
    {
        return DB::transaction(function () use ($cycle, $mainReading, $policy, $customWeights, $actor): int {
            $cycle = BillingCycle::query()->whereKey($cycle->id)->where('organization_id', $mainReading->organization_id)->lockForUpdate()->firstOrFail();
            $mainReading = MeterReading::query()->with('meter')->whereKey($mainReading->id)
                ->where('organization_id', $cycle->organization_id)->lockForUpdate()->firstOrFail();
            if ($mainReading->status !== 'approved' || $mainReading->invoices()->exists()) {
                throw ValidationException::withMessages(['reading' => 'Only approved, unbilled main meter readings can be allocated.']);
            }
            if ($mainReading->reading_at->lt($cycle->starts_on->copy()->startOfDay()) || $mainReading->reading_at->gt($cycle->ends_on->copy()->endOfDay())) {
                throw ValidationException::withMessages(['reading' => 'The main meter reading date must fall inside the selected billing cycle.']);
            }
            if (! in_array($policy, ['equal', 'proportional', 'occupancy', 'floor_area', 'custom', 'landlord_absorbs'], true)) {
                throw ValidationException::withMessages(['policy' => 'Select a supported allocation policy.']);
            }

            $mainAssignment = $mainReading->meter->assignments()->where('organization_id', $cycle->organization_id)
                ->whereDate('starts_at', '<=', $mainReading->reading_at)
                ->where(fn ($query) => $query->whereNull('ends_at')->orWhereDate('ends_at', '>=', $mainReading->reading_at))
                ->whereNull('unit_id')->first();
            if (! $mainAssignment || ! in_array($mainReading->meter->category, ['main', 'shared'], true)) {
                throw ValidationException::withMessages(['reading' => 'This reading is not assigned to a shared or main meter.']);
            }
            if ($cycle->property_id && $cycle->property_id !== $mainAssignment->property_id) {
                throw ValidationException::withMessages(['reading' => 'The main meter does not belong to the property selected for this billing cycle.']);
            }

            if ($mainReading->utilityAllocations()->exists()) {
                throw ValidationException::withMessages(['reading' => 'This reading already has an allocation. Delete or correct that allocation before continuing.']);
            }

            $submeterReadings = MeterReading::query()->with('meter')
                ->where('organization_id', $cycle->organization_id)->where('status', 'approved')
                ->whereBetween('reading_at', [$cycle->starts_on->startOfDay(), $cycle->ends_on->endOfDay()])
                ->where('meter_id', '!=', $mainReading->meter_id)
                ->whereDoesntHave('sharedMeterUsage')
                ->whereHas('meter', fn ($query) => $query->where('utility_type', $mainReading->meter->utility_type))
                ->whereDoesntHave('invoices')->orderByDesc('reading_at')->get();

            $occupancies = Occupancy::query()->with('members')->where('organization_id', $cycle->organization_id)
                ->whereHas('unit', fn ($query) => $query->where('property_id', $mainAssignment->property_id))
                ->whereDate('move_in_date', '<=', $mainReading->reading_at)
                ->where(fn ($query) => $query->whereNull('move_out_date')->orWhereDate('move_out_date', '>=', $mainReading->reading_at))
                ->where('status', 'active')->get()->keyBy('unit_id');
            $byUnit = [];
            foreach ($occupancies as $unitId => $occupancy) {
                $byUnit[$unitId] = ['consumption' => 0, 'reading_id' => null];
            }
            foreach ($submeterReadings as $submeterReading) {
                $assignment = $submeterReading->meter->assignments()
                    ->where('organization_id', $cycle->organization_id)->where('property_id', $mainAssignment->property_id)
                    ->whereDate('starts_at', '<=', $submeterReading->reading_at)
                    ->where(fn ($query) => $query->whereNull('ends_at')->orWhereDate('ends_at', '>=', $submeterReading->reading_at))
                    ->whereNotNull('unit_id')->first();
                if ($assignment && isset($occupancies[$assignment->unit_id]) && $byUnit[$assignment->unit_id]['reading_id'] === null) {
                    $byUnit[$assignment->unit_id] = ['consumption' => (int) round((float) $submeterReading->consumption * 10_000), 'reading_id' => $submeterReading->id];
                }
            }

            $mainUnits = (int) round((float) $mainReading->consumption * 10_000);
            $submeterUnits = array_sum(array_column($byUnit, 'consumption'));
            $varianceUnits = $mainUnits - $submeterUnits;
            if ($varianceUnits < 0 && $policy !== 'landlord_absorbs') {
                throw ValidationException::withMessages(['policy' => 'Submeter use exceeds the main meter. Reconcile the reads or choose landlord absorbs the variance.']);
            }
            if ($byUnit === []) {
                throw ValidationException::withMessages(['reading' => 'There must be at least one active occupancy on the reading date before this main meter can be allocated.']);
            }

            $unitIds = array_map('intval', array_keys($byUnit));
            $floorAreas = Unit::query()->where('organization_id', $cycle->organization_id)->whereIn('id', $unitIds)->pluck('floor_area', 'id');
            $weights = [];
            foreach ($unitIds as $unitId) {
                $base = $byUnit[$unitId]['consumption'];
                $weights[$unitId] = match ($policy) {
                    'proportional' => max(0, $base),
                    'occupancy' => max(1, $occupancies->get($unitId)?->members->count() + 1),
                    'floor_area' => max(0, (int) round((float) ($floorAreas[$unitId] ?? 0) * 100)),
                    'custom' => max(0, (int) round((float) ($customWeights[$unitId] ?? 0) * 10_000)),
                    default => 1,
                };
            }
            if (in_array($policy, ['proportional', 'floor_area'], true) && array_sum($weights) === 0) {
                $weights = array_fill_keys($unitIds, 1);
            }
            if ($policy === 'custom' && array_sum($weights) === 0) {
                throw ValidationException::withMessages(['weights' => 'Enter a positive custom weight for at least one unit.']);
            }

            $adjustUnits = $policy === 'landlord_absorbs' ? 0 : $varianceUnits;
            $weightTotal = array_sum($weights);
            $allocations = [];
            $allocatedAdjustment = 0;
            foreach ($unitIds as $index => $unitId) {
                $adjustment = $index === count($unitIds) - 1
                    ? $adjustUnits - $allocatedAdjustment
                    : (int) round($adjustUnits * ($weights[$unitId] / $weightTotal));
                $allocatedAdjustment += $adjustment;
                $allocated = $byUnit[$unitId]['consumption'] + $adjustment;
                if ($allocated < 0) {
                    throw ValidationException::withMessages(['policy' => 'This allocation would create negative unit consumption. Adjust the meter readings or allocation weights.']);
                }
                $allocations[$unitId] = ['allocated' => $allocated, 'adjustment' => $adjustment];
            }

            foreach ($allocations as $unitId => $allocation) {
                UtilityAllocation::create([
                    'organization_id' => $cycle->organization_id,
                    'meter_reading_id' => $mainReading->id,
                    'source_reading_id' => $byUnit[$unitId]['reading_id'],
                    'unit_id' => $unitId,
                    'tenant_id' => $occupancies->get($unitId)?->tenant_id,
                    'policy' => $policy,
                    'main_consumption' => number_format($mainUnits / 10_000, 4, '.', ''),
                    'submeter_consumption' => number_format($byUnit[$unitId]['consumption'] / 10_000, 4, '.', ''),
                    'allocated_consumption' => number_format($allocation['allocated'] / 10_000, 4, '.', ''),
                    'shared_variance' => number_format($allocation['adjustment'] / 10_000, 4, '.', ''),
                    'landlord_absorbed' => number_format(($policy === 'landlord_absorbs' ? $varianceUnits : 0) / 10_000 / count($unitIds), 4, '.', ''),
                    'calculation_snapshot' => ['cycle_id' => $cycle->id, 'main_reading_id' => $mainReading->id, 'submeter_reading_id' => $byUnit[$unitId]['reading_id'], 'policy' => $policy, 'units_reconciled' => abs(array_sum(array_column($allocations, 'allocated')) + ($policy === 'landlord_absorbs' ? $varianceUnits : 0) - $mainUnits) < 2],
                ]);
            }

            app(AuditLogger::class)->record($cycle->organization_id, $actor->id, 'utility.shared_meter_allocated', $mainReading, null, [
                'policy' => $policy, 'main_consumption' => $mainReading->consumption,
                'submeter_consumption' => number_format($submeterUnits / 10_000, 4, '.', ''),
                'variance' => number_format($varianceUnits / 10_000, 4, '.', ''), 'units' => count($allocations),
            ]);

            return count($allocations);
        });
    }
}
