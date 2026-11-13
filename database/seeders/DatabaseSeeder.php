<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PermissionSeeder::class);
        $this->call(PlatformAdminSeeder::class);
        $starter = SubscriptionPlan::firstOrCreate(
            ['key' => 'starter'],
            ['name' => 'Starter', 'description' => 'Starter workspace plan', 'monthly_price' => 0, 'annual_price' => 0, 'currency' => 'KES', 'limits' => ['properties' => 1, 'units' => 25, 'staff' => 2], 'is_active' => true],
        );

        $user = User::firstOrCreate(['email' => 'test@example.com'], [
            'name' => 'Test User',
            'password' => 'password',
        ]);
        if (! $user->hasVerifiedEmail()) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $organization = Organization::firstOrCreate(
            ['slug' => 'demo-organization'],
            ['name' => 'Demo Organization', 'status' => 'active', 'timezone' => 'Africa/Nairobi'],
        );

        $ownerRole = Role::firstOrCreate(
            ['organization_id' => $organization->id, 'key' => 'owner'],
            ['name' => 'Organization Owner', 'description' => 'Full access to this organization.', 'is_system' => true],
        );

        $organization->users()->sync([
            $user->id => [
                'role_id' => $ownerRole->id,
                'status' => 'active',
                'joined_at' => now(),
            ],
        ], false);
        $organization->subscriptions()->firstOrCreate(
            ['status' => 'trial'],
            ['subscription_plan_id' => $starter->id, 'billing_interval' => 'monthly', 'trial_ends_at' => now()->addDays(14), 'starts_at' => now()],
        );
    }
}
