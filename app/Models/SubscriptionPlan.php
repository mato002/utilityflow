<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = ['name', 'key', 'description', 'monthly_price', 'annual_price', 'currency', 'limits', 'is_active'];

    protected function casts(): array
    {
        return ['monthly_price' => 'decimal:2', 'annual_price' => 'decimal:2', 'limits' => 'array', 'is_active' => 'boolean'];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
