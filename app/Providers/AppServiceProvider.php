<?php

namespace App\Providers;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(5)->by($request->ip()));
        Gate::define('organization-permission', fn (User $user, Organization $organization, string $permission): bool => $user->hasOrganizationPermission($organization, $permission));

        View::composer('layouts.workspace', function (ViewInstance $view): void {
            $workspaceNavigation = [
                'properties' => ['label' => 'Properties', 'icon' => '⌂', 'items' => [
                    ['key' => 'properties', 'label' => 'Properties', 'url' => route('workspace.module', 'properties')],
                    ['key' => 'structure', 'label' => 'Blocks & floors', 'url' => route('workspace.structure')],
                    ['key' => 'spaces', 'label' => 'Units', 'url' => route('workspace.module', 'spaces')],
                    ['key' => 'occupants', 'label' => 'Tenants', 'url' => route('workspace.module', 'occupants')],
                ]],
                'utilities' => ['label' => 'Utilities', 'icon' => '⌁', 'items' => [
                    ['key' => 'meters', 'label' => 'Meters', 'url' => route('workspace.module', 'meters')],
                    ['key' => 'readings', 'label' => 'Readings', 'url' => route('workspace.module', 'readings')],
                    ['key' => 'tariffs', 'label' => 'Tariffs', 'url' => route('workspace.module', 'tariffs')],
                    ['key' => 'allocations', 'label' => 'Shared allocations', 'url' => route('workspace.allocations')],
                ]],
                'billing' => ['label' => 'Billing', 'icon' => '▤', 'items' => [
                    ['key' => 'billing', 'label' => 'Billing & invoices', 'url' => route('workspace.module', 'billing')],
                    ['key' => 'payments', 'label' => 'Payments', 'url' => route('workspace.module', 'payments')],
                ]],
                'operations' => ['label' => 'Operations', 'icon' => '✳', 'items' => [
                    ['key' => 'maintenance', 'label' => 'Maintenance', 'url' => route('workspace.module', 'maintenance')],
                    ['key' => 'notifications', 'label' => 'Notifications', 'url' => route('workspace.module', 'notifications')],
                ]],
                'reports' => ['label' => 'Reports', 'icon' => '▥', 'items' => [
                    ['key' => 'reports', 'label' => 'Reports & insights', 'url' => route('workspace.module', 'reports')],
                ]],
                'manage' => ['label' => 'Manage', 'icon' => '⚙', 'items' => [
                    ['key' => 'team', 'label' => 'Team & access', 'url' => route('workspace.module', 'team')],
                    ['key' => 'settings', 'label' => 'Workspace settings', 'url' => route('workspace.module', 'settings')],
                    ['key' => 'subscription', 'label' => 'Subscription', 'url' => route('workspace.module', 'subscription')],
                    ['key' => 'profile', 'label' => 'My profile', 'url' => route('profile.edit')],
                ]],
            ];
            $navigationGroups = [
                'properties' => 'properties', 'structure' => 'properties', 'spaces' => 'properties', 'occupants' => 'properties',
                'meters' => 'utilities', 'readings' => 'utilities', 'tariffs' => 'utilities', 'allocations' => 'utilities',
                'billing' => 'billing', 'payments' => 'billing', 'maintenance' => 'operations', 'notifications' => 'operations',
                'reports' => 'reports', 'team' => 'manage', 'settings' => 'manage', 'subscription' => 'manage', 'profile' => 'manage',
            ];
            $route = request()->route();
            $routeName = $route?->getName();
            $currentModule = $view->getData()['currentModule'] ?? match ($routeName) {
                'dashboard' => 'overview',
                'workspace.structure' => 'structure',
                'workspace.allocations' => 'allocations',
                'workspace.module' => (string) $route?->parameter('module'),
                'profile.edit' => 'profile',
                default => null,
            };

            $view->with([
                'workspaceNavigation' => $workspaceNavigation,
                'activeNavigationGroup' => $navigationGroups[$currentModule] ?? null,
            ]);
        });
    }
}
