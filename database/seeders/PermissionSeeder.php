<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            'properties' => 'Properties', 'units' => 'Units', 'tenants' => 'Tenants', 'meters' => 'Meters',
            'readings' => 'Meter readings', 'tariffs' => 'Tariffs', 'billing' => 'Billing', 'payments' => 'Payments',
            'maintenance' => 'Maintenance', 'reports' => 'Reports', 'team' => 'Team', 'settings' => 'Settings',
        ];
        $actions = ['view' => 'View', 'create' => 'Create', 'manage' => 'Manage'];

        foreach ($modules as $module => $label) {
            foreach ($actions as $action => $verb) {
                Permission::updateOrCreate(['key' => $module.'.'.$action], ['name' => $verb.' '.$label, 'module' => $module]);
            }
        }
        foreach (['readings.approve' => 'Approve meter readings', 'billing.issue' => 'Issue invoices', 'payments.record' => 'Record payments', 'payments.reverse' => 'Reverse payments'] as $key => $name) {
            Permission::updateOrCreate(['key' => $key], ['name' => $name, 'module' => explode('.', $key)[0]]);
        }
    }
}
