<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CurrentOrganization $currentOrganization): View|RedirectResponse
    {
        $user = $request->user();
        if ($user->is_platform_admin) {
            return redirect()->route('platform.dashboard');
        }

        $organization = $currentOrganization->resolve($user);
        if (! $organization) {
            if ($user->tenantAccounts()->where('status', 'active')->exists()) {
                return redirect()->route('tenant.dashboard');
            }
            abort(403, 'Your account is not connected to an active organization.');
        }

        return view('dashboard', [
            'organization' => $organization,
            'organizations' => $user->organizations()->where('organizations.status', 'active')->orderBy('name')->get(),
            'roleName' => Role::find($organization->users()->where('users.id', $user->id)->first()?->pivot?->role_id)?->name,
            'currentModule' => 'overview',
            'propertyCount' => $organization->properties()->count(),
            'occupiedUnitCount' => $organization->units()->where('occupancy_status', 'occupied')->count(),
            'activeMeterCount' => $organization->meters()->where('status', 'active')->count(),
            'outstandingBalance' => Invoice::where('organization_id', $organization->id)->whereIn('status', ['issued', 'partial', 'overdue'])->selectRaw('COALESCE(SUM(total - amount_paid - credit_total), 0) as balance')->value('balance'),
        ]);
    }

    public function switchOrganization(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($request->user()->organizations()->whereKey($organization->id)->where('organizations.status', 'active')->exists(), 403);

        $request->session()->put('current_organization_id', $organization->id);

        return redirect()->route('dashboard');
    }

    public function platform(Request $request): View
    {
        abort_unless($request->user()->is_platform_admin, 403);

        return view('platform.dashboard', [
            'organizationCount' => Organization::count(),
            'activeOrganizationCount' => Organization::where('status', 'active')->count(),
            'activeSubscriptionCount' => Subscription::where('status', 'active')->count(),
            'userCount' => User::count(),
            'organizations' => Organization::withCount('users')->latest()->limit(8)->get(),
            'plans' => SubscriptionPlan::where('is_active', true)->count(),
        ]);
    }
}
