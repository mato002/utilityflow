<?php

namespace App\Http\Controllers;

use App\Models\BillingCycle;
use App\Models\MeterReading;
use App\Models\Property;
use App\Models\UtilityAllocation;
use App\Services\CurrentOrganization;
use App\Services\SharedMeterAllocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SharedMeterAllocationController extends Controller
{
    public function index(Request $request, CurrentOrganization $organizations): View
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization, 403);
        abort_unless($request->user()->hasOrganizationPermission($organization, 'billing.manage'), 403);

        $cycles = BillingCycle::query()->where('organization_id', $organization->id)->latest('starts_on')->get();
        $cycle = $cycles->firstWhere('id', (int) $request->query('cycle')) ?? $cycles->first();
        $readings = collect();
        if ($cycle) {
            $readings = MeterReading::query()->with(['meter', 'meter.assignments' => fn ($query) => $query->whereNull('unit_id')->with('property.units'), 'utilityAllocations.unit'])
                ->where('organization_id', $organization->id)->where('status', 'approved')
                ->whereBetween('reading_at', [$cycle->starts_on->startOfDay(), $cycle->ends_on->endOfDay()])
                ->whereDoesntHave('invoices')->whereDoesntHave('utilityAllocations')
                ->whereHas('meter', fn ($query) => $query->whereIn('category', ['main', 'shared']))
                ->whereHas('meter.assignments', fn ($query) => $query->whereNull('unit_id')
                    ->whereColumn('starts_at', '<=', 'meter_readings.reading_at')
                    ->where(fn ($q) => $q->whereNull('ends_at')->orWhereColumn('ends_at', '>=', 'meter_readings.reading_at'))
                    ->when($cycle->property_id, fn ($q) => $q->where('property_id', $cycle->property_id)))
                ->orderBy('reading_at')->get();
        }

        $properties = Property::query()->with(['units' => fn ($query) => $query->where('occupancy_status', 'occupied')])
            ->where('organization_id', $organization->id)->orderBy('name')->get();

        return view('workspace.allocations', [
            'organization' => $organization,
            'organizations' => $request->user()->organizations()->where('organizations.status', 'active')->orderBy('name')->get(),
            'roleName' => null,
            'currentModule' => 'allocations',
            'cycles' => $cycles,
            'selectedCycle' => $cycle,
            'readings' => $readings,
            'properties' => $properties,
            'completedAllocations' => UtilityAllocation::query()->with(['reading.meter', 'unit', 'tenant'])
                ->where('organization_id', $organization->id)
                ->whereHas('reading', fn ($query) => $query->when($cycle, fn ($q) => $q->whereBetween('reading_at', [$cycle->starts_on->startOfDay(), $cycle->ends_on->endOfDay()])))
                ->latest()->limit(100)->get(),
        ]);
    }

    public function store(Request $request, CurrentOrganization $organizations, SharedMeterAllocationService $allocator): RedirectResponse
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $request->user()->hasOrganizationPermission($organization, 'billing.manage'), 403);
        $data = $request->validate([
            'billing_cycle_id' => ['required', 'integer', Rule::exists('billing_cycles', 'id')->where('organization_id', $organization->id)],
            'meter_reading_id' => ['required', 'integer', Rule::exists('meter_readings', 'id')->where('organization_id', $organization->id)],
            'policy' => ['required', 'in:equal,proportional,occupancy,floor_area,custom,landlord_absorbs'],
            'weights' => ['nullable', 'array'],
            'weights.*' => ['nullable', 'numeric', 'min:0'],
        ]);
        $cycle = BillingCycle::query()->where('organization_id', $organization->id)->findOrFail($data['billing_cycle_id']);
        $reading = MeterReading::query()->where('organization_id', $organization->id)->findOrFail($data['meter_reading_id']);
        $count = $allocator->allocate($cycle, $reading, $data['policy'], $data['weights'] ?? [], $request->user());

        return to_route('workspace.allocations', ['cycle' => $cycle->id])->with('success', $count.' unit allocations saved. The meter difference is recorded for review.');
    }
}
