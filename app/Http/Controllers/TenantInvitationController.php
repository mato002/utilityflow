<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class TenantInvitationController extends Controller
{
    public function invite(Request $request, Tenant $tenant, CurrentOrganization $organizations, AuditLogger $audit): RedirectResponse
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $tenant->organization_id === $organization->id, 404);
        abort_unless($request->user()->hasOrganizationPermission($organization, 'tenants.manage'), 403);
        if (! $tenant->email) {
            return back()->withErrors(['tenant' => 'Add an email address to this tenant before sending a portal invitation.']);
        }
        if ($tenant->user_id) {
            return back()->with('success', 'This tenant already has portal access.');
        }

        $token = Str::random(64);
        $invitation = TenantInvitation::create([
            'organization_id' => $organization->id, 'tenant_id' => $tenant->id,
            'invited_by' => $request->user()->id, 'email' => strtolower($tenant->email),
            'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7),
        ]);
        $url = route('tenant.invitations.accept', ['token' => $token]);
        try {
            Mail::raw("You have been invited to access your utility account with {$organization->name}. Set up or sign in to your UtilityFlow account: {$url}\n\nThis invitation expires in seven days.", function ($message) use ($tenant): void {
                $message->to($tenant->email)->subject('Your UtilityFlow resident portal invitation');
            });
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['tenant' => 'The invitation could not be emailed. Check mail configuration and try again.']);
        }

        $audit->record($organization->id, $request->user()->id, 'tenant.portal_invited', $tenant, null, ['email' => $tenant->email, 'invitation_id' => $invitation->id], $request);

        return back()->with('success', 'Tenant portal invitation sent.');
    }

    public function acceptForm(string $token): View
    {
        $invitation = $this->invitation($token);
        if (auth()->check()) {
            abort_unless(strtolower(auth()->user()->email) === strtolower($invitation->email), 403, 'Sign in with the email address that received this invitation.');
        }
        $accountExists = User::query()->whereRaw('LOWER(email) = ?', [strtolower($invitation->email)])->exists();

        return view('tenant.invitation', compact('invitation', 'accountExists'));
    }

    public function accept(Request $request, string $token, AuditLogger $audit): RedirectResponse
    {
        $invitation = $this->invitation($token);
        $existingUser = User::query()->whereRaw('LOWER(email) = ?', [strtolower($invitation->email)])->first();
        $newUserData = null;

        if ($existingUser && ! $request->user()) {
            return redirect()->guest(route('login'))->with('status', 'Sign in with the invited email address to continue.');
        }
        if ($request->user()) {
            abort_unless(strtolower($request->user()->email) === strtolower($invitation->email), 403, 'Sign in with the email address that received this invitation.');
            $user = $request->user();
        } else {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:120'],
                'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
            ]);
            $newUserData = $data;
            $user = null;
        }

        $user = DB::transaction(function () use ($invitation, $user, $existingUser, $newUserData, $audit, $request): User {
            $locked = TenantInvitation::query()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->accepted_at || $locked->expires_at->isPast(), 410, 'This invitation has expired or has already been used.');
            $tenant = Tenant::query()->whereKey($locked->tenant_id)->where('organization_id', $locked->organization_id)->lockForUpdate()->firstOrFail();
            $user ??= $existingUser ?? User::create(['name' => $newUserData['name'], 'email' => $locked->email, 'password' => $newUserData['password']]);
            if ($tenant->user_id && $tenant->user_id !== $user->id) {
                abort(409, 'This tenant record is linked to a different portal account. Contact the property team.');
            }
            if (! $user->hasVerifiedEmail()) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
            $tenant->update(['user_id' => $user->id]);
            $locked->update(['accepted_at' => now()]);
            $audit->record($tenant->organization_id, $user->id, 'tenant.portal_activated', $tenant, null, ['user_id' => $user->id, 'invitation_id' => $locked->id], $request);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return to_route('tenant.dashboard')->with('success', 'Your resident portal is ready.');
    }

    private function invitation(string $token): TenantInvitation
    {
        return TenantInvitation::query()->with('tenant.organization')
            ->where('token_hash', hash('sha256', $token))->whereNull('accepted_at')
            ->where('expires_at', '>', now())->firstOrFail();
    }
}
