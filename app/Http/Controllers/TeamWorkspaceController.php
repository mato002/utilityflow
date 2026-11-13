<?php

namespace App\Http\Controllers;

use App\Models\OrganizationInvitation;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use App\Services\CurrentOrganization;
use App\Services\PlanLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamWorkspaceController extends Controller
{
    public function index(Request $request, CurrentOrganization $organizations): View
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization, 403);
        abort_unless($request->user()->hasOrganizationPermission($organization, 'team.view'), 403);

        return view('workspace.team', [
            'organization' => $organization,
            'organizations' => $request->user()->organizations()->where('organizations.status', 'active')->orderBy('name')->get(),
            'roleName' => Role::find($organization->users()->where('users.id', $request->user()->id)->first()?->pivot?->role_id)?->name,
            'members' => $organization->users()->withPivot(['role_id', 'status', 'joined_at'])->orderBy('name')->get(),
            'roles' => $organization->roles()->with(['permissions'])->withCount('permissions')->orderBy('name')->get(),
            'invitations' => OrganizationInvitation::with('role')->where('organization_id', $organization->id)->whereNull('accepted_at')->where('expires_at', '>', now())->latest()->get(),
            'permissions' => Permission::orderBy('module')->orderBy('name')->get()->groupBy('module'),
            'currentModule' => 'team',
        ]);
    }

    public function storeRole(Request $request, CurrentOrganization $organizations, AuditLogger $audit): RedirectResponse
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $request->user()->hasOrganizationPermission($organization, 'team.manage'), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:1000'], 'permission_ids' => ['nullable', 'array'], 'permission_ids.*' => ['integer', Rule::exists('permissions', 'id')]]);
        $role = Role::create(['organization_id' => $organization->id, 'name' => $data['name'], 'key' => Str::slug($data['name']).'-'.Str::lower(Str::random(5)), 'description' => $data['description'] ?? null]);
        $role->permissions()->sync($data['permission_ids'] ?? []);
        $audit->record($organization->id, $request->user()->id, 'role.created', $role, null, $role->only(['name', 'key']));

        return to_route('workspace.module', 'team')->with('success', 'Staff role created.');
    }

    public function updateRole(Request $request, int $roleId, CurrentOrganization $organizations, AuditLogger $audit): RedirectResponse
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $request->user()->hasOrganizationPermission($organization, 'team.manage'), 403);
        $role = Role::query()->where('organization_id', $organization->id)->findOrFail($roleId);
        abort_if($role->is_system || $role->key === 'owner', 422, 'System roles cannot be edited.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', Rule::exists('permissions', 'id')],
        ]);
        $before = $role->only(['name', 'description']);
        $role->update(['name' => $data['name'], 'description' => $data['description'] ?? null]);
        $role->permissions()->sync($data['permission_ids'] ?? []);
        $audit->record($organization->id, $request->user()->id, 'role.updated', $role, $before, $role->only(['name', 'description']), $request);

        return to_route('workspace.module', 'team')->with('success', 'Staff role updated.');
    }

    public function destroyRole(Request $request, int $roleId, CurrentOrganization $organizations, AuditLogger $audit): RedirectResponse
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $request->user()->hasOrganizationPermission($organization, 'team.manage'), 403);
        $role = Role::query()->where('organization_id', $organization->id)->findOrFail($roleId);
        abort_if($role->is_system || $role->key === 'owner', 422, 'System roles cannot be deleted.');

        $isAssigned = DB::table('organization_user')->where('organization_id', $organization->id)->where('role_id', $role->id)->exists();
        $hasPendingInvitations = OrganizationInvitation::query()->where('organization_id', $organization->id)->where('role_id', $role->id)
            ->whereNull('accepted_at')->where('expires_at', '>', now())->exists();
        if ($isAssigned || $hasPendingInvitations) {
            return back()->withErrors(['role' => 'Reassign current members and pending invitations before deleting this role.']);
        }

        $before = $role->only(['name', 'key', 'description']);
        $role->permissions()->detach();
        $role->delete();
        $audit->record($organization->id, $request->user()->id, 'role.deleted', $role, $before, null, $request);

        return to_route('workspace.module', 'team')->with('success', 'Staff role deleted.');
    }

    public function invite(Request $request, CurrentOrganization $organizations, AuditLogger $audit, PlanLimitService $planLimits): RedirectResponse
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $request->user()->hasOrganizationPermission($organization, 'team.manage'), 403);
        $data = $request->validate(['email' => ['required', 'email', 'max:255'], 'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where(fn ($query) => $query->where('organization_id', $organization->id)->where('key', '!=', 'owner'))]]);
        $existingMembership = DB::table('organization_user')
            ->join('users', 'users.id', '=', 'organization_user.user_id')
            ->where('organization_user.organization_id', $organization->id)
            ->whereRaw('LOWER(users.email) = ?', [strtolower($data['email'])])
            ->first(['organization_user.user_id', 'organization_user.status']);
        if ($existingMembership && $existingMembership->status !== 'active') {
            abort_if($existingMembership->role_id === $organization->roles()->where('key', 'owner')->value('id'), 422, 'The organization owner access cannot be reassigned through an invitation.');
            $planLimits->assertCanAdd($organization, 'staff');
            DB::table('organization_user')->where('organization_id', $organization->id)->where('user_id', $existingMembership->user_id)
                ->update(['role_id' => $data['role_id'], 'status' => 'active', 'joined_at' => now(), 'updated_at' => now()]);
            $audit->record($organization->id, $request->user()->id, 'team.member_reactivated', null, ['user_id' => $existingMembership->user_id, 'status' => $existingMembership->status], ['user_id' => $existingMembership->user_id, 'status' => 'active'], $request);

            return to_route('workspace.module', 'team')->with('success', 'Existing member access reactivated with the selected role.');
        }
        if ($organization->users()->where('users.email', $data['email'])->exists()) {
            return back()->withErrors(['email' => 'This person is already a member of this organization.'])->withInput();
        }
        $planLimits->assertCanAdd($organization, 'staff');

        $token = Str::random(64);
        $invitation = OrganizationInvitation::updateOrCreate(
            ['organization_id' => $organization->id, 'email' => strtolower($data['email'])],
            ['role_id' => $data['role_id'], 'invited_by' => $request->user()->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7), 'accepted_at' => null],
        );
        $acceptUrl = route('invitations.accept', ['token' => $token]);
        try {
            Mail::raw("You have been invited to join {$organization->name} on UtilityFlow. Sign in or create an account with this email, then accept your invitation: {$acceptUrl}\n\nThis link expires in seven days.", function ($message) use ($data, $organization): void {
                $message->to($data['email'])->subject('Invitation to '.$organization->name.' on UtilityFlow');
            });
        } catch (\Throwable $exception) {
            report($exception);

            return to_route('workspace.module', 'team')->withErrors(['email' => 'The invitation is saved, but delivery failed. Check mail configuration and resend it.']);
        }
        $audit->record($organization->id, $request->user()->id, 'team.invited', $invitation, null, ['email' => $invitation->email, 'role_id' => $invitation->role_id]);

        return to_route('workspace.module', 'team')->with('success', 'Invitation sent.');
    }

    public function assignRole(Request $request, int $memberId, CurrentOrganization $organizations, AuditLogger $audit): RedirectResponse
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $request->user()->hasOrganizationPermission($organization, 'team.manage'), 403);
        $data = $request->validate(['role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where(fn ($query) => $query->where('organization_id', $organization->id)->where('key', '!=', 'owner'))]]);
        $membership = DB::table('organization_user')->where('organization_id', $organization->id)->where('user_id', $memberId)->first();
        abort_unless($membership, 404);
        abort_if($membership->role_id === $organization->roles()->where('key', 'owner')->value('id'), 422, 'The organization owner role cannot be reassigned.');
        DB::table('organization_user')->where('organization_id', $organization->id)->where('user_id', $memberId)->update(['role_id' => $data['role_id'], 'updated_at' => now()]);
        $audit->record($organization->id, $request->user()->id, 'team.role_changed', null, ['user_id' => $memberId, 'role_id' => $membership->role_id], ['user_id' => $memberId, 'role_id' => $data['role_id']], $request);

        return to_route('workspace.module', 'team')->with('success', 'Member role updated.');
    }

    public function suspend(Request $request, int $memberId, CurrentOrganization $organizations, AuditLogger $audit): RedirectResponse
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $request->user()->hasOrganizationPermission($organization, 'team.manage'), 403);
        abort_unless($request->user()->id !== $memberId, 422, 'You cannot suspend your own organization access.');
        $membership = DB::table('organization_user')->where('organization_id', $organization->id)->where('user_id', $memberId)->first();
        abort_unless($membership, 404);
        abort_if($membership->role_id === $organization->roles()->where('key', 'owner')->value('id'), 422, 'The organization owner cannot be suspended.');
        DB::table('organization_user')->where('organization_id', $organization->id)->where('user_id', $memberId)->update(['status' => 'suspended', 'updated_at' => now()]);
        $audit->record($organization->id, $request->user()->id, 'team.member_suspended', null, ['user_id' => $memberId], ['user_id' => $memberId, 'status' => 'suspended'], $request);

        return to_route('workspace.module', 'team')->with('success', 'Member access suspended.');
    }

    public function accept(Request $request, string $token, AuditLogger $audit, PlanLimitService $planLimits): View|RedirectResponse
    {
        $invitation = OrganizationInvitation::where('token_hash', hash('sha256', $token))->whereNull('accepted_at')->where('expires_at', '>', now())->firstOrFail();
        if (! $request->user()) {
            return redirect()->guest(route('login', ['redirect' => route('invitations.accept', $token)]));
        }
        abort_unless(strtolower($request->user()->email) === strtolower($invitation->email), 403, 'Sign in with the email address that received this invitation.');

        if ($request->isMethod('post')) {
            DB::transaction(function () use ($request, $invitation, $audit, $planLimits): void {
                $invitation = OrganizationInvitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();
                abort_if($invitation->accepted_at || $invitation->expires_at->isPast(), 410);
                $membership = DB::table('organization_user')->where('organization_id', $invitation->organization_id)->where('user_id', $request->user()->id)->first();
                if (! $membership || $membership->status !== 'active') {
                    $planLimits->assertCanAdd($invitation->organization, 'staff');
                    $invitation->organization->users()->syncWithoutDetaching([$request->user()->id => ['role_id' => $invitation->role_id, 'status' => 'active', 'joined_at' => now()]]);
                }
                $invitation->update(['accepted_at' => now()]);
                $audit->record($invitation->organization_id, $request->user()->id, 'team.invitation_accepted', $invitation, null, ['user_id' => $request->user()->id]);
            });
            $request->session()->put('current_organization_id', $invitation->organization_id);

            return to_route('dashboard')->with('success', 'You joined '.$invitation->organization->name.'.');
        }

        return view('invitations.accept', compact('invitation'));
    }
}
