<?php

namespace App\Http\Controllers;

use App\Models\DeviceCredential;
use App\Models\DeviceReading;
use App\Models\Meter;
use App\Models\MeterAssignment;
use App\Models\MeterDevice;
use App\Models\MeterReading;
use App\Models\ReadingAudit;
use App\Services\AuditLogger;
use App\Services\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DeviceWorkspaceController extends Controller
{
    public function replace(Request $request, Meter $meter, CurrentOrganization $organizations, AuditLogger $audit): RedirectResponse
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $meter->organization_id === $organization->id, 404);
        abort_unless($request->user()->hasOrganizationPermission($organization, 'meters.manage'), 403);
        $data = $request->validate([
            'meter_number' => ['required', 'string', 'max:80', Rule::unique('meters', 'meter_number')->where('organization_id', $organization->id)],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'initial_reading' => ['required', 'numeric', 'min:0'],
            'installed_at' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        abort_unless($meter->status === 'active', 422, 'Only an active meter can be replaced.');
        $replacement = DB::transaction(function () use ($meter, $organization, $data, $request, $audit): Meter {
            $assignment = $meter->activeAssignment()->lockForUpdate()->first();
            $installed = Carbon::parse($data['installed_at']);
            if ($assignment && $installed->lessThanOrEqualTo($assignment->starts_at)) {
                throw ValidationException::withMessages(['installed_at' => 'Replacement date must be after the current assignment started.']);
            }
            if ($meter->readings()->where('status', 'approved')->whereDate('reading_at', '>=', $installed->toDateString())->exists()) {
                throw ValidationException::withMessages(['installed_at' => 'Replacement date must be after the latest approved reading for this meter.']);
            }
            $before = $meter->only(['status', 'meter_number']);
            $assignment?->update(['ends_at' => $installed->copy()->subDay()->toDateString()]);
            $meter->update(['status' => 'replaced']);
            $meter->devices()->with('credentials')->get()->each(function (MeterDevice $device): void {
                $device->update(['status' => 'retired']);
                $device->credentials->each(fn (DeviceCredential $credential) => $credential->update(['revoked_at' => now()]));
            });
            $replacement = Meter::create([
                'organization_id' => $organization->id, 'meter_number' => $data['meter_number'],
                'serial_number' => $data['serial_number'] ?? null, 'utility_type' => $meter->utility_type,
                'category' => $meter->category, 'manufacturer' => $meter->manufacturer,
                'installed_at' => $installed->toDateString(), 'initial_reading' => $data['initial_reading'],
                'multiplier' => $meter->multiplier, 'rollover_value' => $meter->rollover_value,
                'status' => 'active', 'notes' => 'Replaced meter '.$meter->meter_number.'. '.$data['reason'],
            ]);
            if ($assignment) {
                MeterAssignment::create([
                    'organization_id' => $organization->id, 'meter_id' => $replacement->id,
                    'property_id' => $assignment->property_id, 'unit_id' => $assignment->unit_id,
                    'starts_at' => $installed->toDateString(), 'assignment_type' => $assignment->assignment_type,
                    'notes' => 'Replacement for meter '.$meter->meter_number,
                ]);
            }
            $audit->record($organization->id, $request->user()->id, 'meter.replaced', $replacement, null, ['replacement_for' => $meter->id, 'meter_number' => $replacement->meter_number], $request);
            $audit->record($organization->id, $request->user()->id, 'meter.retired', $meter, $before, $meter->only(['status', 'meter_number']), $request);

            return $replacement;
        });

        return to_route('workspace.module', 'meters')->with('success', 'Meter replaced. Both assignment histories have been retained.');
    }

    public function create(Request $request, CurrentOrganization $organizations, AuditLogger $audit): RedirectResponse
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $request->user()->hasOrganizationPermission($organization, 'meters.manage'), 403);
        $data = $request->validate([
            'meter_id' => ['required', 'integer', Rule::exists('meters', 'id')->where('organization_id', $organization->id)],
            'provider' => ['required', 'string', 'max:80'],
            'external_id' => ['required', 'string', 'max:120'],
        ]);
        if (MeterDevice::where('provider', $data['provider'])->where('external_id', $data['external_id'])->exists()) {
            return back()->withErrors(['external_id' => 'That provider device ID is already registered.'])->withInput();
        }
        $token = Str::random(64);
        $device = DB::transaction(function () use ($organization, $data, $token): MeterDevice {
            $device = MeterDevice::create(['organization_id' => $organization->id, 'meter_id' => $data['meter_id'], 'provider' => $data['provider'], 'external_id' => $data['external_id'], 'status' => 'active']);
            DeviceCredential::create(['meter_device_id' => $device->id, 'credential_hash' => hash('sha256', $token)]);

            return $device;
        });
        $audit->record($organization->id, $request->user()->id, 'meter_device.created', $device, null, $device->only(['meter_id', 'provider', 'external_id']), $request);

        return to_route('workspace.module', 'meters')->with('device_token', $token)->with('success', 'Device registered. Copy its token now; it will not be shown again.');
    }

    public function reconcile(Request $request, DeviceReading $deviceReading, CurrentOrganization $organizations, AuditLogger $audit): RedirectResponse
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $deviceReading->organization_id === $organization->id, 404);
        abort_unless($request->user()->hasOrganizationPermission($organization, 'readings.approve'), 403);
        abort_unless(in_array($deviceReading->status, ['pending_reconciliation', 'out_of_order'], true), 422);
        $data = $request->validate(['decision' => ['required', Rule::in(['accept', 'reject'])], 'notes' => ['nullable', 'string', 'max:1000']]);

        if ($data['decision'] === 'reject') {
            $deviceReading->update(['status' => 'rejected']);
            $audit->record($organization->id, $request->user()->id, 'device_reading.rejected', $deviceReading, null, ['status' => 'rejected', 'notes' => $data['notes'] ?? null], $request);

            return to_route('workspace.module', 'meters')->with('success', 'Device reading rejected and retained in the raw event history.');
        }

        if ($deviceReading->status === 'out_of_order') {
            return back()->withErrors(['device_reading' => 'Out-of-order device readings require manual review and cannot be automatically reconciled.']);
        }

        $meter = Meter::where('organization_id', $organization->id)->findOrFail($deviceReading->device->meter_id);
        $previous = (float) ($meter->readings()->where('status', 'approved')->latest('reading_at')->value('current_reading') ?? $meter->initial_reading);
        $current = (float) $deviceReading->value;
        abort_if($current < $previous, 422, 'The device value is lower than the last approved reading.');

        DB::transaction(function () use ($organization, $request, $deviceReading, $meter, $previous, $current, $audit, $data): void {
            $reading = MeterReading::create([
                'organization_id' => $organization->id, 'meter_id' => $meter->id,
                'previous_reading' => $previous, 'current_reading' => $current,
                'consumption' => round(($current - $previous) * (float) $meter->multiplier, 4),
                'reading_at' => $deviceReading->measured_at, 'submitted_by' => $request->user()->id,
                'status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now(), 'notes' => $data['notes'] ?? 'Reconciled from smart device event '.$deviceReading->event_id,
            ]);
            ReadingAudit::create(['organization_id' => $organization->id, 'meter_reading_id' => $reading->id, 'actor_id' => $request->user()->id, 'action' => 'device_reconciled', 'after' => $reading->only(['previous_reading', 'current_reading', 'consumption', 'reading_at', 'status'])]);
            $deviceReading->update(['status' => 'reconciled']);
            $audit->record($organization->id, $request->user()->id, 'device_reading.reconciled', $deviceReading, ['status' => 'pending_reconciliation'], ['status' => 'reconciled', 'meter_reading_id' => $reading->id], $request);
        });

        return to_route('workspace.module', 'readings')->with('success', 'Device value reconciled into a billing-reading record.');
    }
}
