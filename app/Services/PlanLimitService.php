<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Validation\ValidationException;

class PlanLimitService
{
    public function assertCanAdd(Organization $organization, string $resource, int $additional = 1): void
    {
        $subscription = $organization->subscription()->with('plan')->where(function ($query): void {
            $query->where(function ($active): void {
                $active->where('status', 'active')->where(function ($dates): void {
                    $dates->whereNull('ends_at')->orWhere('ends_at', '>', now());
                });
            })->orWhere(function ($trial): void {
                $trial->where('status', 'trial')->where('trial_ends_at', '>', now());
            })->orWhere(function ($grace): void {
                $grace->where('status', 'past_due')->where('grace_ends_at', '>', now());
            });
        })->first();
        if (! $subscription || ! $subscription->plan) {
            throw ValidationException::withMessages(['plan' => 'This organization does not have an active subscription plan. Contact your platform administrator.']);
        }

        $limit = data_get($subscription->plan->limits, $resource);
        if ($limit === null) {
            return;
        }

        $count = match ($resource) {
            'properties' => $organization->properties()->count(),
            'units' => $organization->units()->count(),
            'staff' => $organization->users()->wherePivot('role_id', '!=', $organization->roles()->where('key', 'owner')->value('id'))->count(),
            default => 0,
        };
        if ($count + $additional > (int) $limit) {
            throw ValidationException::withMessages(['plan' => "Your current plan allows up to {$limit} {$resource}. Upgrade the plan to add more."]);
        }
    }
}
