<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tariff extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'property_id', 'meter_id', 'name', 'utility_type', 'rate_type', 'version', 'fixed_charge', 'price_per_unit', 'currency', 'effective_from', 'effective_to', 'status'];

    protected function casts(): array
    {
        return ['fixed_charge' => 'decimal:2', 'price_per_unit' => 'decimal:4', 'effective_from' => 'date', 'effective_to' => 'date'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(TariffTier::class)->orderBy('sort_order');
    }
}
