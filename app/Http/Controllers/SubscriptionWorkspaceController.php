<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\AuditLogger;
use App\Services\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SubscriptionWorkspaceController extends Controller
{
    public function __invoke(Request $request, CurrentOrganization $organizations): View
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $request->user()->hasOrganizationPermission($organization, 'settings.view'), 403);

        return view('workspace.subscription', [
            'organization' => $organization,
            'organizations' => $request->user()->organizations()->where('organizations.status', 'active')->orderBy('name')->get(),
            'roleName' => Role::find($organization->users()->where('users.id', $request->user()->id)->first()?->pivot?->role_id)?->name,
            'currentModule' => 'subscription',
            'subscription' => $organization->subscription()->with('plan')->first(),
            'plans' => SubscriptionPlan::query()->where('is_active', true)->orderBy('monthly_price')->get(),
            'pendingPlanRequest' => DB::table('subscription_events')->where('organization_id', $organization->id)->where('event', 'subscription_plan_requested')->latest('id')->get()
                ->first(fn ($event) => (json_decode($event->payload ?? '{}', true)['status'] ?? null) === 'pending_payment_confirmation'),
            'usage' => [
                'properties' => $organization->properties()->count(),
                'units' => $organization->units()->count(),
                'staff' => $organization->users()->wherePivot('role_id', '!=', $organization->roles()->where('key', 'owner')->value('id'))->count(),
            ],
        ]);
    }

    public function changePlan(Request $request, CurrentOrganization $organizations, AuditLogger $audit): RedirectResponse
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $request->user()->hasOrganizationPermission($organization, 'settings.manage'), 403);
        $data = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('subscription_plans', 'id')->where('is_active', true)],
            'interval' => ['required', Rule::in(['monthly', 'annual'])],
            'payment_reference' => ['nullable', 'string', 'max:120'],
        ]);
        $plan = SubscriptionPlan::query()->where('is_active', true)->findOrFail($data['plan_id']);
        $price = (float) ($data['interval'] === 'annual' ? $plan->annual_price : $plan->monthly_price);
        $current = $organization->subscription;

        if ($price > 0) {
            $pendingRequest = DB::table('subscription_events')->where('organization_id', $organization->id)->where('event', 'subscription_plan_requested')->latest('id')->get()
                ->first(fn ($event) => (json_decode($event->payload ?? '{}', true)['status'] ?? null) === 'pending_payment_confirmation');
            if ($pendingRequest) {
                return to_route('profile.edit')->with('success', 'A paid plan request is already waiting for payment confirmation.');
            }
            DB::table('subscription_events')->insert([
                'organization_id' => $organization->id,
                'subscription_id' => $current?->id,
                'event' => 'subscription_plan_requested',
                'payload' => json_encode([
                    'plan_id' => $plan->id,
                    'plan_name' => $plan->name,
                    'interval' => $data['interval'],
                    'amount' => $price,
                    'currency' => $plan->currency,
                    'payment_reference' => $data['payment_reference'] ?? null,
                    'status' => 'pending_payment_confirmation',
                    'requested_by' => $request->user()->id,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $audit->record($organization->id, $request->user()->id, 'subscription.plan_requested', $current, null, ['plan_id' => $plan->id, 'interval' => $data['interval'], 'amount' => $price], $request);

            return to_route('profile.edit')->with('success', 'Your plan request is saved. Complete payment with the UtilityFlow billing team to activate it.');
        }

        DB::transaction(function () use ($organization, $current, $plan, $data, $request, $audit): void {
            if ($current && $current->status !== 'cancelled') {
                $current->update(['status' => 'cancelled', 'ends_at' => now()]);
            }
            $subscription = Subscription::create([
                'organization_id' => $organization->id,
                'subscription_plan_id' => $plan->id,
                'billing_interval' => $data['interval'],
                'status' => 'active',
                'starts_at' => now(),
            ]);
            DB::table('subscription_events')->insert([
                'organization_id' => $organization->id,
                'subscription_id' => $subscription->id,
                'event' => 'subscription_plan_activated',
                'payload' => json_encode(['plan_id' => $plan->id, 'interval' => $data['interval'], 'amount' => 0], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $audit->record($organization->id, $request->user()->id, 'subscription.plan_activated', $subscription, null, $subscription->only(['subscription_plan_id', 'billing_interval', 'status']), $request);
        });

        return to_route('profile.edit')->with('success', 'Your free subscription is active.');
    }
}
