<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlatformAdminController extends Controller
{
    public function organizations(Request $request): View
    {
        $this->authorizePlatform($request);

        return view('platform.organizations', [
            'organizations' => Organization::withCount('users')->with(['subscription.plan'])->latest()->paginate(20),
            'plans' => SubscriptionPlan::where('is_active', true)->orderBy('monthly_price')->get(),
            'allPlans' => SubscriptionPlan::orderBy('monthly_price')->get(),
            'planRequests' => DB::table('subscription_events')->where('event', 'subscription_plan_requested')->latest('id')->limit(100)->get()
                ->map(function ($event) {
                    $event->details = json_decode($event->payload ?? '{}', true) ?: [];

                    return $event;
                })->filter(fn ($event) => ($event->details['status'] ?? null) === 'pending_payment_confirmation')
                ->map(function ($event) {
                    $event->organization = Organization::find($event->organization_id);

                    return $event;
                })->filter(fn ($event) => $event->organization !== null)->values(),
        ]);
    }

    public function settings(Request $request): View
    {
        $this->authorizePlatform($request);
        $settings = PlatformSetting::query()->whereIn('key', ['registration_open', 'default_timezone'])->pluck('value', 'key');

        return view('platform.settings', [
            'registrationOpen' => ($settings['registration_open'] ?? '1') === '1',
            'defaultTimezone' => $settings['default_timezone'] ?? 'Africa/Nairobi',
            'auditLogs' => AuditLog::query()->with(['actor:id,name,email', 'organization:id,name'])
                ->latest()->paginate(25),
            'timezoneOptions' => timezone_identifiers_list(),
        ]);
    }

    public function updateSettings(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->authorizePlatform($request);
        $data = $request->validate([
            'default_timezone' => ['required', 'timezone'],
            'registration_open' => ['nullable', 'boolean'],
        ]);
        $before = PlatformSetting::query()->whereIn('key', ['registration_open', 'default_timezone'])->pluck('value', 'key')->all();
        $after = [
            'registration_open' => $request->boolean('registration_open') ? '1' : '0',
            'default_timezone' => $data['default_timezone'],
        ];

        foreach ($after as $key => $value) {
            PlatformSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $audit->record(null, $request->user()->id, 'platform.settings_updated', null, $before, $after, $request);

        return to_route('platform.settings')->with('success', 'Platform settings saved.');
    }

    public function setOrganizationStatus(Request $request, Organization $organization, AuditLogger $audit): RedirectResponse
    {
        $this->authorizePlatform($request);
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'suspended', 'inactive'])], 'reason' => ['nullable', 'string', 'max:1000']]);
        $before = ['status' => $organization->status];
        $organization->update(['status' => $data['status']]);
        $audit->record(null, $request->user()->id, 'platform.organization_status_changed', $organization, $before, ['status' => $organization->status, 'reason' => $data['reason'] ?? null], $request);

        return to_route('platform.organizations')->with('success', 'Organization status updated.');
    }

    public function assignPlan(Request $request, Organization $organization, AuditLogger $audit): RedirectResponse
    {
        $this->authorizePlatform($request);
        $data = $request->validate(['plan_id' => ['required', 'integer', Rule::exists('subscription_plans', 'id')->where('is_active', true)], 'interval' => ['required', Rule::in(['monthly', 'annual'])], 'status' => ['required', Rule::in(['trial', 'active', 'past_due', 'suspended', 'cancelled'])]]);
        abort_unless($organization->exists, 404);
        $subscription = DB::transaction(function () use ($organization, $data): Subscription {
            $current = $organization->subscription;
            if ($current && $current->status !== 'cancelled') {
                $current->update(['status' => 'cancelled', 'ends_at' => now()]);
            }

            return Subscription::create([
                'organization_id' => $organization->id, 'subscription_plan_id' => $data['plan_id'],
                'billing_interval' => $data['interval'], 'status' => $data['status'],
                'trial_ends_at' => $data['status'] === 'trial' ? now()->addDays(14) : null,
                'starts_at' => $data['status'] === 'active' ? now() : null,
            ]);
        });
        $audit->record(null, $request->user()->id, 'platform.subscription_assigned', $subscription, null, $subscription->only(['subscription_plan_id', 'billing_interval', 'status']), $request);

        return to_route('platform.organizations')->with('success', 'Organization plan updated.');
    }

    public function storePlan(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->authorizePlatform($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:1000'],
            'monthly_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'], 'annual_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'currency' => ['required', 'string', 'size:3'], 'is_active' => ['nullable', 'boolean'],
            'max_properties' => ['nullable', 'integer', 'min:0'], 'max_units' => ['nullable', 'integer', 'min:0'], 'max_staff' => ['nullable', 'integer', 'min:0'],
        ]);
        $plan = SubscriptionPlan::create([
            'name' => $data['name'], 'key' => Str::slug($data['name']).'-'.Str::lower(Str::random(5)), 'description' => $data['description'] ?? null,
            'monthly_price' => $data['monthly_price'], 'annual_price' => $data['annual_price'], 'currency' => strtoupper($data['currency']),
            'limits' => array_filter(['properties' => $data['max_properties'] ?? null, 'units' => $data['max_units'] ?? null, 'staff' => $data['max_staff'] ?? null], fn ($v) => $v !== null),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
        $audit->record(null, $request->user()->id, 'platform.subscription_plan_created', $plan, null, $plan->only(['name', 'key', 'monthly_price', 'annual_price', 'currency', 'limits', 'is_active']), $request);

        return to_route('platform.organizations')->with('success', 'Subscription plan created.');
    }

    public function updatePlan(Request $request, SubscriptionPlan $plan, AuditLogger $audit): RedirectResponse
    {
        $this->authorizePlatform($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:1000'],
            'monthly_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'], 'annual_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'currency' => ['required', 'string', 'size:3'], 'is_active' => ['nullable', 'boolean'],
            'max_properties' => ['nullable', 'integer', 'min:0'], 'max_units' => ['nullable', 'integer', 'min:0'], 'max_staff' => ['nullable', 'integer', 'min:0'],
        ]);
        $before = $plan->only(['name', 'description', 'monthly_price', 'annual_price', 'currency', 'limits', 'is_active']);
        $plan->update([
            'name' => $data['name'], 'description' => $data['description'] ?? null,
            'monthly_price' => $data['monthly_price'], 'annual_price' => $data['annual_price'],
            'currency' => strtoupper($data['currency']),
            'limits' => array_filter(['properties' => $data['max_properties'] ?? null, 'units' => $data['max_units'] ?? null, 'staff' => $data['max_staff'] ?? null], fn ($value) => $value !== null),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);
        $audit->record(null, $request->user()->id, 'platform.subscription_plan_updated', $plan, $before, $plan->only(['name', 'description', 'monthly_price', 'annual_price', 'currency', 'limits', 'is_active']), $request);

        return to_route('platform.organizations')->with('success', 'Subscription plan updated.');
    }

    public function setPlanStatus(Request $request, SubscriptionPlan $plan, AuditLogger $audit): RedirectResponse
    {
        $this->authorizePlatform($request);
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $before = ['is_active' => $plan->is_active];
        $plan->update(['is_active' => (bool) $data['is_active']]);
        $audit->record(null, $request->user()->id, 'platform.subscription_plan_status_changed', $plan, $before, ['is_active' => $plan->is_active], $request);

        return to_route('platform.organizations')->with('success', $plan->is_active ? 'Plan is available for new assignments.' : 'Plan is archived; current subscribers retain their access.');
    }

    public function resolvePlanRequest(Request $request, int $eventId, AuditLogger $audit): RedirectResponse
    {
        $this->authorizePlatform($request);
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])]]);

        return DB::transaction(function () use ($eventId, $data, $request, $audit): RedirectResponse {
            $event = DB::table('subscription_events')->where('id', $eventId)->where('event', 'subscription_plan_requested')->lockForUpdate()->first();
            abort_unless($event, 404);
            $payload = json_decode($event->payload ?? '{}', true) ?: [];
            abort_unless(($payload['status'] ?? null) === 'pending_payment_confirmation', 422, 'This plan request has already been resolved.');
            $organization = Organization::findOrFail($event->organization_id);
            $before = $payload;

            if ($data['decision'] === 'approve') {
                $plan = SubscriptionPlan::query()->where('is_active', true)->findOrFail($payload['plan_id'] ?? 0);
                $current = $organization->subscription;
                if ($current && $current->status !== 'cancelled') {
                    $current->update(['status' => 'cancelled', 'ends_at' => now()]);
                }
                $subscription = Subscription::create([
                    'organization_id' => $organization->id,
                    'subscription_plan_id' => $plan->id,
                    'billing_interval' => $payload['interval'],
                    'status' => 'active',
                    'starts_at' => now(),
                ]);
                $payload['status'] = 'approved';
                $payload['approved_subscription_id'] = $subscription->id;
                $payload['resolved_by'] = $request->user()->id;
                $payload['resolved_at'] = now()->toIso8601String();
                DB::table('subscription_events')->where('id', $event->id)->update(['payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
                $audit->record($organization->id, $request->user()->id, 'subscription.plan_request_approved', $subscription, null, $subscription->only(['subscription_plan_id', 'billing_interval', 'status']), $request);

                return to_route('platform.organizations')->with('success', 'Payment-confirmed subscription request approved.');
            }

            $payload['status'] = 'rejected';
            $payload['resolved_by'] = $request->user()->id;
            $payload['resolved_at'] = now()->toIso8601String();
            DB::table('subscription_events')->where('id', $event->id)->update(['payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
            $audit->record($organization->id, $request->user()->id, 'subscription.plan_request_rejected', null, $before, $payload, $request);

            return to_route('platform.organizations')->with('success', 'Subscription request rejected. The current plan remains unchanged.');
        });
    }

    private function authorizePlatform(Request $request): void
    {
        abort_unless($request->user()?->is_platform_admin, 403);
    }
}
