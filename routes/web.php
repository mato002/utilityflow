<?php

use App\Http\Controllers\AccountProfileController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\OrganizationRegistrationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\BillingWorkflowController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DarajaPaymentController;
use App\Http\Controllers\DeviceWorkspaceController;
use App\Http\Controllers\MaintenanceWorkflowController;
use App\Http\Controllers\MeterReadingWorkflowController;
use App\Http\Controllers\NotificationWorkspaceController;
use App\Http\Controllers\OrganizationSettingsController;
use App\Http\Controllers\PaymentWorkflowController;
use App\Http\Controllers\PlatformAdminController;
use App\Http\Controllers\PropertyStructureController;
use App\Http\Controllers\ReportsWorkspaceController;
use App\Http\Controllers\SharedMeterAllocationController;
use App\Http\Controllers\SubscriptionWorkspaceController;
use App\Http\Controllers\TeamWorkspaceController;
use App\Http\Controllers\TenantInvitationController;
use App\Http\Controllers\TenantPortalController;
use App\Http\Controllers\TenantRecordsController;
use App\Http\Controllers\WorkspaceModuleController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'marketing.home')->name('home');
Route::get('/robots.txt', function () {
    return response("User-agent: *\nAllow: /\nDisallow: /account\nDisallow: /dashboard\nDisallow: /platform\nDisallow: /tenant\nDisallow: /workspace\nSitemap: ".url('/sitemap.xml')."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
})->name('robots');
Route::get('/sitemap.xml', function () {
    $home = htmlspecialchars(route('home'), ENT_XML1 | ENT_QUOTES, 'UTF-8');

    return response('<?xml version="1.0" encoding="UTF-8"?>'.
        '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>'.$home.'</loc><changefreq>monthly</changefreq><priority>1.0</priority></url></urlset>',
        200,
        ['Content-Type' => 'application/xml; charset=UTF-8'],
    );
})->name('sitemap');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
    Route::get('/register', [OrganizationRegistrationController::class, 'create'])->name('register');
    Route::post('/register', [OrganizationRegistrationController::class, 'store'])->middleware('throttle:register');
    Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:6,1')->name('password.update');
});

Route::get('/invitations/{token}', [TeamWorkspaceController::class, 'accept'])->name('invitations.accept');
Route::post('/invitations/{token}', [TeamWorkspaceController::class, 'accept'])->middleware(['auth', 'verified']);
Route::get('/tenant/invitations/{token}', [TenantInvitationController::class, 'acceptForm'])->name('tenant.invitations.accept');
Route::post('/tenant/invitations/{token}', [TenantInvitationController::class, 'accept'])->middleware('throttle:10,1')->name('tenant.invitations.store');

Route::middleware('auth')->group(function (): void {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])->middleware('throttle:6,1')->name('verification.send');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/account', [AccountProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/account', [AccountProfileController::class, 'update'])->name('profile.update');
    Route::put('/account/palette', [AccountProfileController::class, 'updatePalette'])->name('profile.palette.update');
    Route::put('/account/password', [AccountProfileController::class, 'updatePassword'])->middleware('throttle:6,1')->name('profile.password.update');
    Route::get('/dashboard', DashboardController::class)->middleware('verified')->name('dashboard');
    Route::middleware('verified')->group(function (): void {
        Route::get('/tenant', [TenantPortalController::class, 'index'])->name('tenant.dashboard');
        Route::post('/tenant/issues', [TenantPortalController::class, 'reportIssue'])->name('tenant.issues.store');
        Route::post('/tenant/payments/daraja', [DarajaPaymentController::class, 'initiate'])->name('tenant.payments.daraja');
        Route::get('/tenant/usage', [TenantRecordsController::class, 'usage'])->name('tenant.usage');
        Route::get('/tenant/invoices/{invoice}', [TenantRecordsController::class, 'invoice'])->name('tenant.invoices.show');
        Route::get('/tenant/payments/{payment}/receipt', [TenantRecordsController::class, 'receipt'])->name('tenant.payments.receipt');
    });
    Route::post('/workspace/{organization}/switch', [DashboardController::class, 'switchOrganization'])
        ->middleware('verified')->name('workspace.switch');

    Route::middleware(['organization', 'verified'])->group(function (): void {
        Route::get('/water-reading', [MeterReadingWorkflowController::class, 'index'])->name('water.readings');
        Route::post('/workspace/tenants/{tenant}/invite', [TenantInvitationController::class, 'invite'])->name('tenants.invite');
        Route::get('/workspace/structure', [PropertyStructureController::class, 'index'])->name('workspace.structure');
        Route::post('/workspace/structure/blocks', [PropertyStructureController::class, 'storeBlock'])->name('workspace.structure.blocks.store');
        Route::put('/workspace/structure/blocks/{block}', [PropertyStructureController::class, 'updateBlock'])->name('workspace.structure.blocks.update');
        Route::delete('/workspace/structure/blocks/{block}', [PropertyStructureController::class, 'destroyBlock'])->name('workspace.structure.blocks.destroy');
        Route::post('/workspace/structure/floors', [PropertyStructureController::class, 'storeFloor'])->name('workspace.structure.floors.store');
        Route::put('/workspace/structure/floors/{floor}', [PropertyStructureController::class, 'updateFloor'])->name('workspace.structure.floors.update');
        Route::delete('/workspace/structure/floors/{floor}', [PropertyStructureController::class, 'destroyFloor'])->name('workspace.structure.floors.destroy');
        Route::get('/workspace/allocations', [SharedMeterAllocationController::class, 'index'])->name('workspace.allocations');
        Route::post('/workspace/allocations', [SharedMeterAllocationController::class, 'store'])->name('workspace.allocations.store');
        Route::get('/workspace/subscription', SubscriptionWorkspaceController::class)->name('workspace.subscription');
        Route::get('/workspace/{module}', WorkspaceModuleController::class)->name('workspace.module');
        Route::post('/workspace/readings', [MeterReadingWorkflowController::class, 'store'])->name('readings.store');
        Route::post('/workspace/{module}/records', [WorkspaceModuleController::class, 'store'])->name('workspace.records.store');
        Route::put('/workspace/{module}/records/{record}', [WorkspaceModuleController::class, 'update'])->name('workspace.records.update');
        Route::delete('/workspace/{module}/records/{record}', [WorkspaceModuleController::class, 'destroy'])->name('workspace.records.destroy');
        Route::post('/workspace/readings/{reading}/approve', [MeterReadingWorkflowController::class, 'approve'])->name('readings.approve');
        Route::post('/workspace/readings/{reading}/reject', [MeterReadingWorkflowController::class, 'reject'])->name('readings.reject');
        Route::get('/workspace/reports/export', [ReportsWorkspaceController::class, 'export'])->name('reports.export');
        Route::put('/workspace/settings', [OrganizationSettingsController::class, 'update'])->name('organization.settings.update');
        Route::post('/workspace/subscription', [SubscriptionWorkspaceController::class, 'changePlan'])->name('subscription.change');
        Route::put('/workspace/maintenance/{ticket}', [MaintenanceWorkflowController::class, 'update'])->name('maintenance.update');
        Route::post('/workspace/notifications', [NotificationWorkspaceController::class, 'send'])->name('notifications.send');
        Route::post('/workspace/notification-templates', [NotificationWorkspaceController::class, 'storeTemplate'])->name('notifications.templates.store');
        Route::put('/workspace/notification-templates/{template}', [NotificationWorkspaceController::class, 'updateTemplate'])->name('notifications.templates.update');
        Route::delete('/workspace/notification-templates/{template}', [NotificationWorkspaceController::class, 'destroyTemplate'])->name('notifications.templates.destroy');
        Route::post('/workspace/devices', [DeviceWorkspaceController::class, 'create'])->name('devices.store');
        Route::post('/workspace/meters/{meter}/replace', [DeviceWorkspaceController::class, 'replace'])->name('meters.replace');
        Route::post('/workspace/device-readings/{deviceReading}/reconcile', [DeviceWorkspaceController::class, 'reconcile'])->name('device-readings.reconcile');
        Route::post('/workspace/billing/cycles', [BillingWorkflowController::class, 'storeCycle'])->name('billing.cycles.store');
        Route::post('/workspace/billing/cycles/{cycle}/generate', [BillingWorkflowController::class, 'generate'])->name('billing.cycles.generate');
        Route::post('/workspace/billing/invoices/{invoice}/issue', [BillingWorkflowController::class, 'issue'])->name('billing.invoices.issue');
        Route::post('/workspace/billing/invoices/{invoice}/credit-notes', [BillingWorkflowController::class, 'credit'])->name('billing.invoices.credit');
        Route::post('/workspace/payments', [PaymentWorkflowController::class, 'store'])->name('payments.store');
        Route::post('/workspace/payments/{payment}/daraja/reconcile', [PaymentWorkflowController::class, 'reconcileDaraja'])->name('payments.daraja.reconcile');
        Route::post('/workspace/payments/{payment}/apply-credit', [PaymentWorkflowController::class, 'applyCredit'])->name('payments.apply-credit');
        Route::post('/workspace/payments/{payment}/reverse', [PaymentWorkflowController::class, 'reverse'])->name('payments.reverse');
        Route::post('/workspace/team/invitations', [TeamWorkspaceController::class, 'invite'])->name('team.invite');
        Route::post('/workspace/team/roles', [TeamWorkspaceController::class, 'storeRole'])->name('team.roles.store');
        Route::put('/workspace/team/roles/{roleId}', [TeamWorkspaceController::class, 'updateRole'])->name('team.roles.update');
        Route::delete('/workspace/team/roles/{roleId}', [TeamWorkspaceController::class, 'destroyRole'])->name('team.roles.destroy');
        Route::put('/workspace/team/members/{memberId}/role', [TeamWorkspaceController::class, 'assignRole'])->name('team.members.role');
        Route::post('/workspace/team/members/{memberId}/suspend', [TeamWorkspaceController::class, 'suspend'])->name('team.members.suspend');
    });

    Route::middleware('verified')->group(function (): void {
        Route::get('/platform', [DashboardController::class, 'platform'])->name('platform.dashboard');
        Route::get('/platform/organizations', [PlatformAdminController::class, 'organizations'])->name('platform.organizations');
        Route::get('/platform/settings', [PlatformAdminController::class, 'settings'])->name('platform.settings');
        Route::put('/platform/settings', [PlatformAdminController::class, 'updateSettings'])->name('platform.settings.update');
        Route::put('/platform/organizations/{organization}/status', [PlatformAdminController::class, 'setOrganizationStatus'])->name('platform.organizations.status');
        Route::put('/platform/organizations/{organization}/subscription', [PlatformAdminController::class, 'assignPlan'])->name('platform.organizations.subscription');
        Route::put('/platform/subscription-requests/{eventId}', [PlatformAdminController::class, 'resolvePlanRequest'])->name('platform.subscription-requests.resolve');
        Route::post('/platform/plans', [PlatformAdminController::class, 'storePlan'])->name('platform.plans.store');
        Route::put('/platform/plans/{plan}', [PlatformAdminController::class, 'updatePlan'])->name('platform.plans.update');
        Route::patch('/platform/plans/{plan}/status', [PlatformAdminController::class, 'setPlanStatus'])->name('platform.plans.status');
    });
});
