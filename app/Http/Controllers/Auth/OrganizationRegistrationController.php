<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\PlatformSetting;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class OrganizationRegistrationController extends Controller
{
    public function create(): View
    {
        abort_unless($this->registrationIsOpen(), 403, 'New workspace registration is currently paused.');

        return view('auth.register');
    }

    public function store(): RedirectResponse
    {
        abort_unless($this->registrationIsOpen(), 403, 'New workspace registration is currently paused.');

        $data = request()->validate([
            'organization_name' => ['required', 'string', 'max:160'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        $defaultTimezone = PlatformSetting::query()->where('key', 'default_timezone')->value('value') ?? 'Africa/Nairobi';

        [$user, $organization] = DB::transaction(function () use ($data, $defaultTimezone): array {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $baseSlug = Str::slug($data['organization_name']);
            $slug = $baseSlug;
            for ($suffix = 2; Organization::where('slug', $slug)->exists(); $suffix++) {
                $slug = $baseSlug.'-'.$suffix;
            }

            $organization = Organization::create([
                'name' => $data['organization_name'],
                'slug' => $slug,
                'status' => 'active',
                'timezone' => $defaultTimezone,
            ]);

            $ownerRole = Role::firstOrCreate(
                ['organization_id' => $organization->id, 'key' => 'owner'],
                ['name' => 'Organization Owner', 'description' => 'Full access to this organization.', 'is_system' => true],
            );

            $organization->users()->attach($user, [
                'role_id' => $ownerRole->id,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            $starter = SubscriptionPlan::firstOrCreate(
                ['key' => 'starter'],
                ['name' => 'Starter', 'description' => 'Starter workspace plan', 'monthly_price' => 0, 'annual_price' => 0, 'currency' => 'KES', 'limits' => ['properties' => 1, 'units' => 25, 'staff' => 2], 'is_active' => true],
            );
            Subscription::create([
                'organization_id' => $organization->id,
                'subscription_plan_id' => $starter->id,
                'billing_interval' => 'monthly',
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(14),
                'starts_at' => now(),
            ]);

            return [$user, $organization];
        });

        Auth::login($user);
        request()->session()->regenerate();
        request()->session()->put('current_organization_id', $organization->id);

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('verification.notice')->withErrors(['email' => 'Your account was created, but the verification email could not be sent. Use the resend option after checking the mail configuration.']);
        }

        return redirect()->route('verification.notice')->with('status', 'verification-link-sent');
    }

    private function registrationIsOpen(): bool
    {
        return PlatformSetting::query()->where('key', 'registration_open')->value('value') !== '0';
    }
}
