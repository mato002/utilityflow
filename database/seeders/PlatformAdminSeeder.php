<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('platform_admin.email');
        $configuredPassword = config('platform_admin.password');

        if (! app()->isLocal() && (! $email || ! $configuredPassword)) {
            $this->command?->warn('Platform admin was not seeded. Configure PLATFORM_ADMIN_EMAIL and PLATFORM_ADMIN_PASSWORD first.');

            return;
        }

        $email ??= 'platform-admin@utilityflow.test';
        $password = $configuredPassword ?: Str::random(40);
        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Platform Administrator',
                'password' => $password,
                'email_verified_at' => now(),
                'is_platform_admin' => true,
            ],
        );

        $admin->forceFill([
            'email_verified_at' => $admin->email_verified_at ?? now(),
            'is_platform_admin' => true,
        ])->save();

        if ($admin->wasRecentlyCreated && ! $configuredPassword) {
            $this->command?->info('Platform admin email: '.$email);
            $this->command?->warn('One-time generated password: '.$password);
            $this->command?->warn('Save this password now. Set PLATFORM_ADMIN_PASSWORD to manage it through environment configuration.');
        }
    }
}
