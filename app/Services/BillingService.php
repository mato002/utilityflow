<?php

namespace App\Services;

use App\Models\BillingCycle;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MeterReading;
use App\Models\Tariff;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BillingService
{
    public function __construct(private readonly TariffCalculator $calculator, private readonly AuditLogger $audit) {}

    /** @return array<int, Invoice> */
    public function generate(BillingCycle $cycle, User $actor): array
    {
        return DB::transaction(function () use ($cycle, $actor): array {
            $cycle = BillingCycle::query()->whereKey($cycle->id)->where('organization_id', $cycle->organization_id)->lockForUpdate()->firstOrFail();
            if ($cycle->status !== 'draft' || $cycle->invoices()->exists()) {
                throw ValidationException::withMessages(['cycle' => 'This billing cycle has already been processed.']);
            }

            $readings = MeterReading::query()->with(['meter', 'utilityAllocations.unit'])
                ->where('organization_id', $cycle->organization_id)
                ->where('status', 'approved')
                ->whereBetween('reading_at', [$cycle->starts_on->startOfDay(), $cycle->ends_on->endOfDay()])
                ->whereDoesntHave('invoices')
                ->whereDoesntHave('sharedMeterUsage')
                ->orderBy('reading_at')->lockForUpdate()->get();

            $groups = [];
            foreach ($readings as $reading) {
                $assignment = $reading->meter->assignments()
                    ->where('organization_id', $cycle->organization_id)
                    ->whereDate('starts_at', '<=', $reading->reading_at)
                    ->where(fn ($query) => $query->whereNull('ends_at')->orWhereDate('ends_at', '>=', $reading->reading_at))
                    ->when($cycle->property_id, fn ($query) => $query->where('property_id', $cycle->property_id))
                    ->first();
                if (! $assignment) {
                    continue;
                }

                $targets = [];
                if ($assignment->unit_id) {
                    $occupancy = $assignment->unit->occupancies()
                        ->where('organization_id', $cycle->organization_id)
                        ->whereDate('move_in_date', '<=', $reading->reading_at)
                        ->where(fn ($query) => $query->whereNull('move_out_date')->orWhereDate('move_out_date', '>=', $reading->reading_at))
                        ->first();
                    if ($occupancy) {
                        $targets[] = ['unit_id' => $assignment->unit_id, 'tenant_id' => $occupancy->tenant_id, 'consumption' => (string) $reading->consumption, 'allocation' => null];
                    }
                } else {
                    if (in_array($reading->meter->category, ['main', 'shared'], true) && $reading->utilityAllocations->isEmpty()) {
                        throw ValidationException::withMessages(['allocation' => 'Allocate shared meter '.$reading->meter->meter_number.' before generating this billing cycle.']);
                    }
                    foreach ($reading->utilityAllocations as $allocation) {
                        if (! $allocation->tenant_id) {
                            continue;
                        }
                        $targets[] = ['unit_id' => $allocation->unit_id, 'tenant_id' => $allocation->tenant_id, 'consumption' => (string) $allocation->allocated_consumption, 'allocation' => $allocation];
                    }
                }
                if ($targets === []) {
                    continue;
                }

                $tariff = Tariff::query()->where('organization_id', $cycle->organization_id)
                    ->where('utility_type', $reading->meter->utility_type)->where('status', 'active')
                    ->whereDate('effective_from', '<=', $reading->reading_at)
                    ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $reading->reading_at))
                    ->where(fn ($query) => $query->where('meter_id', $reading->meter_id)->orWhereNull('meter_id'))
                    ->where(fn ($query) => $query->where('property_id', $assignment->property_id)->orWhereNull('property_id'))
                    ->orderByRaw('meter_id IS NULL')->orderByRaw('property_id IS NULL')->orderByDesc('version')->first();
                if (! $tariff) {
                    throw ValidationException::withMessages(['tariff' => 'No active tariff covers '.$reading->meter->utility_type.' for '.$reading->reading_at->toDateString().'.']);
                }

                foreach ($targets as $target) {
                    $calculation = $this->calculator->calculate($tariff, $target['consumption']);
                    $key = $target['tenant_id'].'-'.$target['unit_id'];
                    $groups[$key] ??= ['tenant_id' => $target['tenant_id'], 'unit_id' => $target['unit_id'], 'items' => []];
                    foreach ($calculation['items'] as $item) {
                        $item['utility_type'] = $reading->meter->utility_type;
                        $item['snapshot'] = ['reading_id' => $reading->id, 'tariff_id' => $tariff->id, 'tariff_version' => $tariff->version, 'allocation_id' => $target['allocation']?->id, 'allocation_policy' => $target['allocation']?->policy, 'allocated_consumption' => $target['consumption'], 'shared_meter' => $target['allocation'] !== null, 'shared_variance' => $target['allocation']?->shared_variance, 'landlord_absorbed' => $target['allocation']?->landlord_absorbed];
                        $groups[$key]['items'][] = [$item, $reading, $tariff];
                    }
                }
            }

            if ($groups === []) {
                throw ValidationException::withMessages(['cycle' => 'No unbilled approved readings with an active unit occupancy were found in this cycle.']);
            }

            $invoices = [];
            foreach ($groups as $group) {
                $invoice = Invoice::create([
                    'organization_id' => $cycle->organization_id,
                    'billing_cycle_id' => $cycle->id,
                    'tenant_id' => $group['tenant_id'],
                    'unit_id' => $group['unit_id'],
                    'invoice_number' => 'UF-'.now()->format('ym').'-'.Str::upper(Str::random(8)),
                    'issued_on' => now()->toDateString(),
                    'due_on' => $cycle->due_on,
                    'subtotal' => '0.00',
                    'tax_total' => '0.00',
                    'total' => '0.00',
                    'amount_paid' => '0.00',
                    'currency' => 'KES',
                    'status' => 'draft',
                    'created_by' => $actor->id,
                ]);

                $totalCents = 0;
                foreach ($group['items'] as [$item, $reading, $tariff]) {
                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'description' => $item['description'],
                        'utility_type' => $item['utility_type'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'amount' => $item['amount'],
                        'calculation_snapshot' => $item['snapshot'] + ['tariff' => $tariff->only(['name', 'rate_type', 'fixed_charge', 'price_per_unit', 'currency'])],
                    ]);
                    $totalCents += Money::cents($item['amount']);
                    $invoice->readings()->syncWithoutDetaching([$reading->id => [
                        'consumption' => $item['snapshot']['allocated_consumption'] ?? $reading->consumption,
                        'tariff_snapshot' => json_encode($tariff->only(['id', 'name', 'version', 'rate_type', 'fixed_charge', 'price_per_unit']) + [
                            'shared_meter' => $item['snapshot']['shared_meter'] ?? false,
                            'allocation_policy' => $item['snapshot']['allocation_policy'] ?? null,
                            'shared_variance' => $item['snapshot']['shared_variance'] ?? null,
                        ]),
                    ]]);
                }

                $invoice->update(['subtotal' => Money::decimal($totalCents), 'total' => Money::decimal($totalCents)]);
                $this->audit->record($cycle->organization_id, $actor->id, 'invoice.drafted', $invoice, null, $invoice->only(['invoice_number', 'tenant_id', 'unit_id', 'total', 'status']));
                $invoices[] = $invoice->fresh(['items']);
            }

            $cycle->update(['status' => 'invoiced']);

            return $invoices;
        });
    }

    public function issue(Invoice $invoice, User $actor): Invoice
    {
        return DB::transaction(function () use ($invoice, $actor): Invoice {
            $invoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($invoice->status !== 'draft' || Money::cents((string) $invoice->total) <= 0) {
                throw ValidationException::withMessages(['invoice' => 'Only non-empty draft invoices can be issued.']);
            }

            $before = $invoice->only(['status', 'issued_at', 'issued_on']);
            $invoice->update(['status' => 'issued', 'issued_at' => now(), 'issued_on' => now()->toDateString()]);
            $this->audit->record($invoice->organization_id, $actor->id, 'invoice.issued', $invoice, $before, $invoice->only(['status', 'issued_at', 'issued_on']));

            return $invoice->fresh();
        });
    }
}
