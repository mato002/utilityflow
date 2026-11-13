<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'subscription_plan_id', 'billing_interval', 'status', 'trial_ends_at', 'starts_at', 'ends_at', 'grace_ends_at'];

    protected function casts(): array
    {
        return ['trial_ends_at' => 'datetime', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'grace_ends_at' => 'datetime'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }
}
