<?php

namespace App\Http\Controllers;

use App\Models\Meter;
use App\Models\MeterAssignment;
use App\Models\MeterReading;
use App\Models\Property;
use App\Models\Role;
use App\Services\CurrentOrganization;
use App\Services\MeterReadingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MeterReadingWorkflowController extends Controller
{
    public function index(Request $request, CurrentOrganization $organizations): View
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization, 403);
        $canViewReadings = $request->user()->hasOrganizationPermission($organization, 'readings.view');
        $canCreateReadings = $request->user()->hasOrganizationPermission($organization, 'readings.create');
        abort_unless($canViewReadings || $canCreateReadings, 403);

        $properties = Property::query()->where('organization_id', $organization->id)->where('status', 'active')->orderBy('name')->get();
        $selectedProperty = $properties->firstWhere('id', $request->integer('property_id'));
        $propertyUnits = $selectedProperty?->units()->with('block')->orderBy('unit_number')->get() ?? collect();
        $assignments = $selectedProperty
            ? MeterAssignment::query()
                ->with([
                    'unit',
                    'meter' => fn ($query) => $query->where('status', 'active')->where('utility_type', 'water')->addSelect(['last_approved_reading' => MeterReading::query()
                        ->select('current_reading')
                        ->whereColumn('meter_id', 'meters.id')
                        ->where('status', 'approved')
                        ->orderByDesc('reading_at')
                        ->limit(1)]),
                ])
                ->where('organization_id', $organization->id)
                ->where('property_id', $selectedProperty->id)
                ->whereNull('ends_at')
                ->whereDate('starts_at', '<=', today())
                ->where(fn ($query) => $query->whereNull('unit_id')->orWhereHas('unit', fn ($unitQuery) => $unitQuery->where('organization_id', $organization->id)->where('property_id', $selectedProperty->id)))
                ->whereHas('meter', fn ($query) => $query->where('organization_id', $organization->id)->where('status', 'active')->where('utility_type', 'water'))
                ->orderByDesc('starts_at')
                ->get()
                ->unique('meter_id')->values()
            : collect();

        $readings = $canViewReadings
            ? MeterReading::query()->with(['meter.activeAssignment.property', 'meter.activeAssignment.unit', 'reader'])
                ->where('organization_id', $organization->id)->latest('reading_at')->paginate(10)
            : MeterReading::query()->whereRaw('1 = 0')->paginate(10);
        $meters = Meter::query()
            ->where('organization_id', $organization->id)
            ->where('status', 'active')
            ->addSelect(['last_approved_reading' => MeterReading::query()
                ->select('current_reading')
                ->whereColumn('meter_id', 'meters.id')
                ->where('status', 'approved')
                ->orderByDesc('reading_at')
                ->limit(1)])
            ->orderBy('meter_number')
            ->get();

        if ($request->routeIs('water.readings')) {
            return view('workspace.water-reader', [
                'organization' => $organization,
                'organizations' => $request->user()->organizations()->where('organizations.status', 'active')->orderBy('name')->get(),
                'roleName' => $organization->users()->where('users.id', $request->user()->id)->first()?->pivot?->role_id
                    ? Role::find($organization->users()->where('users.id', $request->user()->id)->first()->pivot->role_id)?->name : null,
                'properties' => $properties,
                'selectedProperty' => $selectedProperty,
                'propertyUnits' => $propertyUnits,
                'unitAssignments' => $assignments->filter(fn (MeterAssignment $assignment): bool => $assignment->unit_id !== null && $assignment->meter !== null)->groupBy('unit_id'),
                'sharedAssignments' => $assignments->filter(fn (MeterAssignment $assignment): bool => $assignment->unit_id === null && $assignment->meter !== null),
                'meters' => $meters,
                'readings' => $readings,
                'canCreateReadings' => $canCreateReadings,
                'canViewReadings' => $canViewReadings,
                'currentModule' => 'readings',
            ]);
        }

        return view('workspace.readings', [
            'organization' => $organization,
            'organizations' => $request->user()->organizations()->where('organizations.status', 'active')->orderBy('name')->get(),
            'roleName' => $organization->users()->where('users.id', $request->user()->id)->first()?->pivot?->role_id
                ? Role::find($organization->users()->where('users.id', $request->user()->id)->first()->pivot->role_id)?->name : null,
            'readings' => $readings,
            'meters' => $meters,
            'canCreateReadings' => $canCreateReadings,
            'currentModule' => 'readings',
        ]);
    }

    public function store(Request $request, CurrentOrganization $organizations, MeterReadingService $service): RedirectResponse
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization, 403);
        abort_unless($request->user()->hasOrganizationPermission($organization, 'readings.create'), 403);

        if ($request->has('readings')) {
            $data = $request->validate([
                'property_id' => ['required', 'integer', Rule::exists('properties', 'id')->where('organization_id', $organization->id)],
                'water_reader' => ['nullable', 'boolean'],
                'reading_at' => ['required', 'date'],
                'readings' => ['required', 'array', 'min:1', 'max:500'],
                'readings.*.meter_id' => ['required', 'integer', Rule::exists('meters', 'id')->where('organization_id', $organization->id)],
                'readings.*.current_reading' => ['nullable', 'numeric', 'min:0', 'max:999999999999.9999'],
                'readings.*.submission_uuid' => ['required', 'uuid'],
            ]);
            $filledReadings = collect($data['readings'])->filter(fn (array $reading): bool => filled($reading['current_reading'] ?? null));
            if ($filledReadings->isEmpty()) {
                return back()->withErrors(['readings' => 'Enter at least one current water reading.'])->withInput();
            }

            Property::query()->where('organization_id', $organization->id)->where('status', 'active')->findOrFail($data['property_id']);
            $allowedMeterIds = MeterAssignment::query()
                ->where('organization_id', $organization->id)
                ->where('property_id', $data['property_id'])
                ->whereNull('ends_at')
                ->whereDate('starts_at', '<=', today())
                ->where(fn ($query) => $query->whereNull('unit_id')->orWhereHas('unit', fn ($unitQuery) => $unitQuery->where('organization_id', $organization->id)->where('property_id', $data['property_id'])))
                ->whereHas('meter', fn ($query) => $query->where('organization_id', $organization->id)->where('status', 'active')->where('utility_type', 'water'))
                ->pluck('meter_id')->all();
            $submittedMeterIds = $filledReadings->pluck('meter_id')->map(fn ($meterId): int => (int) $meterId);
            if ($submittedMeterIds->count() !== $submittedMeterIds->unique()->count() || $submittedMeterIds->diff($allowedMeterIds)->isNotEmpty()) {
                return back()->withErrors(['readings' => 'One or more meters are not active water meters assigned to the selected property. Refresh the page and try again.'])->withInput();
            }

            DB::transaction(function () use ($filledReadings, $data, $organization, $request, $service): void {
                foreach ($filledReadings as $readingData) {
                    $meter = Meter::query()->where('organization_id', $organization->id)->findOrFail($readingData['meter_id']);
                    $service->submit($meter, $request->user(), (float) $readingData['current_reading'], $data['reading_at'], submissionUuid: $readingData['submission_uuid']);
                }
            });

            if (($data['water_reader'] ?? false) === true || ($data['water_reader'] ?? null) === '1') {
                return to_route('water.readings', ['property_id' => $data['property_id']])->with('success', $filledReadings->count().' water reading(s) submitted for review.');
            }

            return to_route('workspace.module', 'readings')->with('success', $filledReadings->count().' water reading(s) submitted for review.');
        }

        $data = $request->validate([
            'submission_uuid' => ['required', 'uuid'],
            'meter_id' => ['required', 'integer', Rule::exists('meters', 'id')->where('organization_id', $organization->id)],
            'current_reading' => ['required', 'numeric', 'min:0', 'max:999999999999.9999'],
            'reading_at' => ['required', 'date'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (MeterReading::query()->where('organization_id', $organization->id)->where('submission_uuid', $data['submission_uuid'])->exists()) {
            return to_route('workspace.module', 'readings')->with('success', 'This offline reading was already synchronized. No duplicate was created.');
        }

        $meter = Meter::query()->where('organization_id', $organization->id)->findOrFail($data['meter_id']);
        $photoPath = $request->file('photo')?->store('meter-readings/'.$organization->id, 'local');
        $reading = $service->submit($meter, $request->user(), (float) $data['current_reading'], $data['reading_at'], $photoPath, $data['notes'] ?? null, $data['submission_uuid']);
        $reading->update(['latitude' => $data['latitude'] ?? null, 'longitude' => $data['longitude'] ?? null]);

        return to_route('workspace.module', 'readings')->with('success', 'Reading submitted for approval.');
    }

    public function approve(Request $request, MeterReading $reading, CurrentOrganization $organizations, MeterReadingService $service): RedirectResponse
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $reading->organization_id === $organization->id, 404);
        abort_unless($request->user()->hasOrganizationPermission($organization, 'readings.approve'), 403);

        $service->approve($reading, $request->user());

        return to_route('workspace.module', 'readings')->with('success', 'Meter reading approved.');
    }

    public function reject(Request $request, MeterReading $reading, CurrentOrganization $organizations, MeterReadingService $service): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $reading->organization_id === $organization->id, 404);
        abort_unless($request->user()->hasOrganizationPermission($organization, 'readings.approve'), 403);

        $service->reject($reading, $request->user(), $data['reason']);

        return to_route('workspace.module', 'readings')->with('success', 'Meter reading rejected with an audit note.');
    }
}
