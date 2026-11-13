<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Services\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class AccountProfileController extends Controller
{
    public function edit(Request $request, CurrentOrganization $organizations): View
    {
        if ($request->user()->is_platform_admin) {
            return view('platform.profile');
        }

        $organization = $organizations->resolve($request->user());
        if ($organization) {
            return view('workspace.profile', [
                'organization' => $organization,
                'organizations' => $request->user()->organizations()->where('organizations.status', 'active')->orderBy('name')->get(),
                'roleName' => Role::find($organization->users()->where('users.id', $request->user()->id)->first()?->pivot?->role_id)?->name,
                'currentModule' => 'profile',
                'pageTitle' => 'My profile',
                'subscription' => $organization->subscription()->with('plan')->first(),
                'plans' => SubscriptionPlan::query()->where('is_active', true)->orderBy('monthly_price')->get(),
                'pendingPlanRequest' => DB::table('subscription_events')->where('organization_id', $organization->id)->where('event', 'subscription_plan_requested')->latest('id')->get()
                    ->first(fn ($event) => (json_decode($event->payload ?? '{}', true)['status'] ?? null) === 'pending_payment_confirmation'),
                'colorPalettes' => config('brand_palettes'),
            ]);
        }

        return view('account.profile');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $request->user()->update(['name' => $data['name']]);

        return to_route('profile.edit')->with('success', 'Your profile was updated.');
    }

    public function updatePalette(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'color_palette' => ['required', Rule::in(array_keys(config('brand_palettes')))],
        ]);

        $request->user()->update($data);

        return to_route('profile.edit')->with('success', 'Your personal color palette was updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $request->user()->forceFill(['password' => Hash::make($data['password'])])->save();
        $request->session()->regenerate();

        return to_route('profile.edit')->with('password-success', 'Your password was changed.');
    }
}
