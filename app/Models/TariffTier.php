<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TariffTier extends Model
{
    protected $fillable = ['tariff_id', 'from_units', 'to_units', 'price_per_unit', 'sort_order'];

    protected function casts(): array
    {
        return ['from_units' => 'decimal:4', 'to_units' => 'decimal:4', 'price_per_unit' => 'decimal:4'];
    }

    public function tariff(): BelongsTo
    {
        return $this->belongsTo(Tariff::class);
    }
}
