<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceTicket;
use App\Models\MaintenanceUpdate;
use App\Models\Role;
use App\Services\AuditLogger;
use App\Services\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MaintenanceWorkflowController extends Controller
{
    public function index(Request $request, CurrentOrganization $organizations): View
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $request->user()->hasOrganizationPermission($organization, 'maintenance.view'), 403);

        return view('workspace.maintenance', [
            'organization' => $organization,
            'organizations' => $request->user()->organizations()->where('organizations.status', 'active')->orderBy('name')->get(),
            'roleName' => Role::find($organization->users()->where('users.id', $request->user()->id)->first()?->pivot?->role_id)?->name,
            'tickets' => MaintenanceTicket::with(['property', 'unit', 'assignee', 'tenant'])->where('organization_id', $organization->id)->latest()->paginate(12),
            'properties' => $organization->properties()->orderBy('name')->get(),
            'units' => $organization->units()->with('property')->orderBy('unit_number')->get(),
            'staff' => $organization->users()->wherePivot('status', 'active')->orderBy('name')->get(),
            'currentModule' => 'maintenance',
        ]);
    }

    public function update(Request $request, MaintenanceTicket $ticket, CurrentOrganization $organizations, AuditLogger $audit): RedirectResponse
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $ticket->organization_id === $organization->id, 404);
        abort_unless($request->user()->hasOrganizationPermission($organization, 'maintenance.manage'), 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'assigned', 'in_progress', 'resolved', 'closed'])],
            'assigned_to' => ['nullable', 'integer', Rule::exists('organization_user', 'user_id')->where('organization_id', $organization->id)->where('status', 'active')],
            'body' => ['required', 'string', 'max:3000'],
        ]);
        $before = $ticket->only(['status', 'assigned_to']);
        $ticket->update(['status' => $data['status'], 'assigned_to' => $data['assigned_to'] ?? null, 'resolved_at' => in_array($data['status'], ['resolved', 'closed'], true) ? now() : null]);
        MaintenanceUpdate::create(['maintenance_ticket_id' => $ticket->id, 'user_id' => $request->user()->id, 'status' => $data['status'], 'body' => $data['body']]);
        $audit->record($organization->id, $request->user()->id, 'maintenance.updated', $ticket, $before, $ticket->only(['status', 'assigned_to']), $request);

        return to_route('workspace.module', 'maintenance')->with('success', 'Maintenance ticket updated.');
    }
}
