<?php

namespace App\Http\Controllers;

use App\Models\DeviceReading;
use App\Models\Meter;
use App\Models\MeterDevice;
use App\Models\Role;
use App\Models\WorkspaceRecord;
use App\Services\AuditLogger;
use App\Services\CurrentOrganization;
use App\Services\DomainWorkspaceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkspaceModuleController extends Controller
{
    private const FORM_FIELDS = [
        'properties' => [['type', 'Property type', 'select', ['Residential', 'Commercial', 'Mixed use', 'Estate', 'Other']], ['address', 'Street address', 'text'], ['city', 'City or town', 'text'], ['units_count', 'Number of units', 'number']],
        'spaces' => [['property', 'Property', 'select', []], ['unit_type', 'Unit type', 'select', ['Apartment', 'Office', 'Shop', 'House', 'Other']], ['floor', 'Floor', 'select', []], ['occupancy', 'Occupancy', 'select', ['Vacant', 'Occupied', 'Reserved', 'Maintenance']]],
        'occupants' => [['unit', 'Unit', 'select', []], ['phone', 'Phone number', 'tel'], ['email', 'Email address', 'email'], ['move_in', 'Move-in date', 'date']],
        'meters' => [['utility', 'Utility', 'select', ['water', 'electricity']], ['property', 'Property', 'select', []], ['unit', 'Unit', 'select', []], ['serial', 'Serial number', 'text'], ['category', 'Meter category', 'select', ['individual', 'main', 'submeter', 'shared']], ['manufacturer', 'Manufacturer', 'text'], ['installed_at', 'Installation date', 'date'], ['initial_reading', 'Initial reading', 'number'], ['multiplier', 'Multiplier', 'number'], ['rollover_value', 'Rollover value', 'number']],
        'readings' => [['meter', 'Meter number', 'text'], ['previous_reading', 'Previous reading', 'number'], ['current_reading', 'Current reading', 'number'], ['reader', 'Reader', 'text']],
        'tariffs' => [['utility', 'Utility', 'select', ['water', 'electricity']], ['rate_type', 'Rate type', 'select', ['per_unit', 'fixed', 'tiered']], ['unit_rate', 'Rate per unit', 'number'], ['fixed_charge', 'Fixed charge', 'number'], ['tier_1_from', 'Tier 1 from units', 'number'], ['tier_1_to', 'Tier 1 up to units', 'number'], ['tier_1_rate', 'Tier 1 rate', 'number'], ['tier_2_from', 'Tier 2 from units', 'number'], ['tier_2_to', 'Tier 2 up to units', 'number'], ['tier_2_rate', 'Tier 2 rate', 'number'], ['tier_3_from', 'Tier 3 from units', 'number'], ['tier_3_to', 'Tier 3 up to units', 'number'], ['tier_3_rate', 'Tier 3 rate', 'number']],
        'billing' => [['tenant', 'Tenant', 'text'], ['billing_period', 'Billing period', 'text'], ['due_date', 'Due date', 'date'], ['currency', 'Currency', 'text']],
        'payments' => [['invoice', 'Invoice reference', 'text'], ['method', 'Payment method', 'select', ['M-Pesa', 'Bank transfer', 'Cash', 'Card', 'Other']], ['transaction_id', 'Transaction ID', 'text'], ['received_by', 'Received by', 'text']],
        'maintenance' => [['property', 'Property', 'select', []], ['unit', 'Unit', 'select', []], ['category', 'Issue type', 'select', ['leak', 'meter_fault', 'electricity_fault', 'other']], ['priority', 'Priority', 'select', ['low', 'normal', 'high', 'urgent']], ['assigned_to', 'Assigned technician ID', 'number']],
        'reports' => [['report_type', 'Report type', 'select', ['Consumption', 'Billing', 'Collections', 'Property activity']], ['period_start', 'Period starts', 'date'], ['period_end', 'Period ends', 'date']],
        'team' => [['email', 'Work email', 'email'], ['role', 'Role', 'select', ['Property manager', 'Utility manager', 'Meter reader', 'Accountant', 'Technician', 'Support']], ['phone', 'Phone number', 'tel']],
        'notifications' => [['channel', 'Channel', 'select', ['Email', 'SMS', 'WhatsApp']], ['recipient', 'Recipient', 'text'], ['event', 'Event', 'text']],
        'settings' => [['category', 'Category', 'text'], ['value', 'Value', 'text'], ['description', 'Description', 'textarea']],
    ];

    private const MODULES = [
        'properties' => [
            'title' => 'Properties', 'icon' => '⌂',
            'record' => 'property', 'plural' => 'properties', 'empty_title' => 'No properties yet',
        ],
        'spaces' => [
            'title' => 'Units', 'icon' => '▦',
            'record' => 'unit', 'plural' => 'units', 'empty_title' => 'No units yet',
        ],
        'occupants' => [
            'title' => 'Tenants', 'icon' => '♧',
            'record' => 'tenant', 'plural' => 'tenants', 'empty_title' => 'No tenants yet',
        ],
        'meters' => [
            'title' => 'Meters', 'icon' => '⌁',
            'record' => 'meter', 'plural' => 'meters', 'empty_title' => 'No meters yet',
        ],
        'readings' => [
            'title' => 'Meter readings', 'icon' => '◷',
            'record' => 'reading', 'plural' => 'readings', 'empty_title' => 'No readings yet',
        ],
        'tariffs' => [
            'title' => 'Tariffs', 'icon' => '◈',
            'record' => 'tariff', 'plural' => 'tariffs', 'empty_title' => 'No tariffs yet',
        ],
        'billing' => [
            'title' => 'Billing & invoices', 'icon' => '▤',
            'record' => 'invoice', 'plural' => 'invoices', 'empty_title' => 'No invoices yet',
        ],
        'payments' => [
            'title' => 'Payments', 'icon' => '↗',
            'record' => 'payment', 'plural' => 'payments', 'empty_title' => 'No payments yet',
        ],
        'maintenance' => [
            'title' => 'Maintenance', 'icon' => '✳',
            'record' => 'ticket', 'plural' => 'tickets', 'empty_title' => 'No tickets yet',
        ],
        'reports' => [
            'title' => 'Reports & insights', 'icon' => '▥',
            'record' => 'report', 'plural' => 'reports', 'empty_title' => 'No reports yet',
        ],
        'team' => [
            'title' => 'Team & access', 'icon' => '♙',
            'record' => 'team member', 'plural' => 'team members', 'empty_title' => 'No team members yet',
        ],
        'notifications' => [
            'title' => 'Notifications', 'icon' => '♧',
            'record' => 'notification', 'plural' => 'notifications', 'empty_title' => 'No notifications yet',
        ],
        'settings' => [
            'title' => 'Workspace settings', 'icon' => '⚙',
            'record' => 'setting', 'plural' => 'settings', 'empty_title' => 'No settings yet',
        ],
    ];

    public function __invoke(Request $request, string $module, CurrentOrganization $currentOrganization): View
    {
        if ($module === 'subscription') {
            return app(SubscriptionWorkspaceController::class)($request, $currentOrganization);
        }

        abort_unless(isset(self::MODULES[$module]), 404);

        if ($module === 'readings') {
            return app(MeterReadingWorkflowController::class)->index($request, $currentOrganization);
        }
        if ($module === 'billing') {
            return app(BillingWorkflowController::class)->index($request, $currentOrganization);
        }
        if ($module === 'payments') {
            return app(PaymentWorkflowController::class)->index($request, $currentOrganization);
        }
        if ($module === 'team') {
            return app(TeamWorkspaceController::class)->index($request, $currentOrganization);
        }
        if ($module === 'reports') {
            return app(ReportsWorkspaceController::class)->index($request, $currentOrganization);
        }
        if ($module === 'settings') {
            return app(OrganizationSettingsController::class)->index($request, $currentOrganization);
        }
        if ($module === 'maintenance') {
            return app(MaintenanceWorkflowController::class)->index($request, $currentOrganization);
        }
        if ($module === 'notifications') {
            return app(NotificationWorkspaceController::class)->index($request, $currentOrganization);
        }
        $organization = $currentOrganization->resolve($request->user());
        abort_unless($organization, 403, 'Your account is not connected to an active organization.');

        $user = $request->user();
        abort_unless($user->hasOrganizationPermission($organization, $this->permissionFor($module, 'view')), 403);
        $config = self::MODULES[$module];
        $config['key'] = $module;

        $domain = app(DomainWorkspaceService::class);
        $records = $domain->supports($module)
            ? $domain->records($module, $organization->id, $request->string('search')->trim()->value())
            : WorkspaceRecord::query()->where('organization_id', $organization->id)->where('module', $module)
                ->when($request->string('search')->trim()->value(), function ($query, string $search): void {
                    $query->where(function ($query) use ($search): void {
                        $query->where('title', 'like', '%'.$search.'%')->orWhere('reference', 'like', '%'.$search.'%')->orWhere('summary', 'like', '%'.$search.'%');
                    });
                })->latest()->paginate(10)->withQueryString();

        return view('workspace.module', [
            'organization' => $organization,
            'organizations' => $user->organizations()->where('organizations.status', 'active')->orderBy('name')->get(),
            'roleName' => Role::find($organization->users()->where('users.id', $user->id)->first()?->pivot?->role_id)?->name,
            'module' => $config,
            'currentModule' => $module,
            'records' => $records,
            'formFields' => $domain->supports($module) ? $domain->fields($module, $organization->id) : $this->formFields($module),
            'statuses' => $domain->supports($module) ? $domain->statuses($module) : $this->statuses($module),
            'devices' => $module === 'meters' ? MeterDevice::with('meter')->where('organization_id', $organization->id)->latest()->get() : collect(),
            'meters' => $module === 'meters' ? Meter::where('organization_id', $organization->id)->where('status', 'active')->orderBy('meter_number')->get() : collect(),
            'deviceReadings' => $module === 'meters' ? DeviceReading::with(['device.meter'])->where('organization_id', $organization->id)->whereIn('status', ['pending_reconciliation', 'out_of_order'])->latest('measured_at')->limit(30)->get() : collect(),
        ]);
    }

    public function store(Request $request, string $module, CurrentOrganization $currentOrganization)
    {
        abort_unless(isset(self::MODULES[$module]), 404);
        $organization = $currentOrganization->resolve($request->user());
        abort_unless($organization, 403);
        abort_unless($request->user()->hasOrganizationPermission($organization, $this->permissionFor($module, 'create')), 403);

        $domain = app(DomainWorkspaceService::class);
        if ($domain->supports($module)) {
            $domain->save($request, $module, $organization->id, $request->user());

            return to_route('workspace.module', $module)->with('success', ucfirst($this->moduleRecordName($module)).' created successfully.');
        }

        $data = $this->validated($request, $module);
        $data['organization_id'] = $organization->id;
        $data['module'] = $module;
        $data['values'] = $data['values'] ?? [];

        $record = WorkspaceRecord::create($data);
        app(AuditLogger::class)->record($organization->id, $request->user()->id, $module.'.created', $record, null, ['title' => $record->title, 'reference' => $record->reference], $request);

        return to_route('workspace.module', $module)->with('success', 'Record created successfully.');
    }

    public function update(Request $request, string $module, int $record, CurrentOrganization $currentOrganization)
    {
        abort_unless(isset(self::MODULES[$module]), 404);
        $organization = $currentOrganization->resolve($request->user());
        abort_unless($organization, 403);
        abort_unless($request->user()->hasOrganizationPermission($organization, $this->permissionFor($module, 'manage')), 403);
        $domain = app(DomainWorkspaceService::class);
        if ($domain->supports($module)) {
            $domain->save($request, $module, $organization->id, $request->user(), $record);

            return to_route('workspace.module', $module)->with('success', ucfirst($this->moduleRecordName($module)).' updated successfully.');
        }
        $record = WorkspaceRecord::query()->where('organization_id', $organization->id)->where('module', $module)->findOrFail($record);
        $this->assertOwnedRecord($record, $organization->id, $module);
        if (! $this->canUpdate($module, $record)) {
            return to_route('workspace.module', $module)->with('error', 'This record is locked to preserve its financial or reading history.');
        }

        $before = ['title' => $record->title, 'reference' => $record->reference, 'status' => $record->status];
        $data = $this->validated($request, $module, $record);
        $data['values'] = $data['values'] ?? [];
        $record->update($data);
        app(AuditLogger::class)->record($organization->id, $request->user()->id, $module.'.updated', $record, $before, ['title' => $record->title, 'reference' => $record->reference, 'status' => $record->status], $request);

        return to_route('workspace.module', $module)->with('success', 'Record updated successfully.');
    }

    public function destroy(Request $request, string $module, int $record, CurrentOrganization $currentOrganization)
    {
        abort_unless(isset(self::MODULES[$module]), 404);
        $organization = $currentOrganization->resolve($request->user());
        abort_unless($organization, 403);
        abort_unless($request->user()->hasOrganizationPermission($organization, $this->permissionFor($module, 'manage')), 403);
        $domain = app(DomainWorkspaceService::class);
        if ($domain->supports($module)) {
            $domain->delete($module, $organization->id, $request->user(), $record, $request);

            return to_route('workspace.module', $module)->with('success', ucfirst($this->moduleRecordName($module)).' removed or deleted successfully.');
        }
        $record = WorkspaceRecord::query()->where('organization_id', $organization->id)->where('module', $module)->findOrFail($record);
        $this->assertOwnedRecord($record, $organization->id, $module);

        if (! $this->canDelete($module, $record)) {
            return to_route('workspace.module', $module)->with('error', 'This financial record is locked to preserve its audit history.');
        }

        $before = ['title' => $record->title, 'reference' => $record->reference, 'status' => $record->status];
        $record->delete();
        app(AuditLogger::class)->record($organization->id, $request->user()->id, $module.'.deleted', $record, $before, null, $request);

        return to_route('workspace.module', $module)->with('success', 'Record deleted successfully.');
    }

    private function validated(Request $request, string $module, ?WorkspaceRecord $record = null): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:180'],
            'reference' => ['nullable', 'string', 'max:100', Rule::unique('workspace_records', 'reference')->where(fn ($query) => $query->where('organization_id', app(CurrentOrganization::class)->resolve($request->user())?->id)->where('module', $module))->ignore($record?->id)],
            'status' => ['required', Rule::in($this->statuses($module))],
            'summary' => ['nullable', 'string', 'max:3000'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'recorded_on' => ['nullable', 'date'],
            'values' => ['nullable', 'array:'.implode(',', array_map(fn (array $field): string => $field[0], self::FORM_FIELDS[$module]))],
        ];

        foreach ($this->formFields($module) as [$key, $label, $type, $options]) {
            $rule = match ($type) {
                'number' => ['nullable', 'numeric', 'min:0'],
                'date' => ['nullable', 'date'],
                'email' => ['nullable', 'email', 'max:255'],
                'select' => ['nullable', Rule::in($options)],
                'textarea' => ['nullable', 'string', 'max:3000'],
                default => ['nullable', 'string', 'max:255'],
            };
            $rules['values.'.$key] = $rule;
        }

        return Validator::make($request->all(), $rules)->validate();
    }

    private function assertOwnedRecord(WorkspaceRecord $record, int $organizationId, string $module): void
    {
        abort_unless($record->organization_id === $organizationId && $record->module === $module, 404);
    }

    private function canDelete(string $module, WorkspaceRecord $record): bool
    {
        $status = strtolower($record->status);
        if ($module === 'payments') {
            return false;
        }

        if ($module === 'billing' && $status !== 'draft') {
            return false;
        }

        return $module !== 'readings' || in_array($status, ['draft', 'rejected'], true);
    }

    private function canUpdate(string $module, WorkspaceRecord $record): bool
    {
        $status = strtolower($record->status);

        return match ($module) {
            'billing' => $status === 'draft',
            'payments' => $status === 'pending reconciliation',
            'readings' => in_array($status, ['draft', 'rejected'], true),
            default => true,
        };
    }

    private function statuses(string $module): array
    {
        return match ($module) {
            'spaces' => ['Vacant', 'Occupied', 'Reserved', 'Maintenance'],
            'meters' => ['Active', 'Inactive', 'Faulty', 'Maintenance', 'Removed', 'Replaced'],
            'readings' => ['Draft', 'Submitted', 'Pending approval', 'Approved', 'Rejected'],
            'billing' => ['Draft', 'Issued', 'Partial', 'Paid', 'Overdue', 'Cancelled'],
            'payments' => ['Received', 'Pending reconciliation', 'Reconciled', 'Reversed'],
            'maintenance' => ['Open', 'Assigned', 'In progress', 'Resolved', 'Closed'],
            'team' => ['Invited', 'Active', 'Suspended'],
            'notifications' => ['Draft', 'Scheduled', 'Sent', 'Failed'],
            default => ['Active', 'Inactive'],
        };
    }

    private function formFields(string $module): array
    {
        return array_map(fn (array $field): array => array_pad($field, 4, []), self::FORM_FIELDS[$module]);
    }

    private function moduleRecordName(string $module): string
    {
        return self::MODULES[$module]['record'] ?? 'record';
    }

    private function permissionFor(string $module, string $action): string
    {
        $resource = match ($module) {
            'spaces' => 'units', 'occupants' => 'tenants', default => $module,
        };

        return $resource.'.'.$action;
    }
}
