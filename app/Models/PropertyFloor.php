<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PropertyFloor extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'property_id', 'block_id', 'name', 'level'];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(PropertyBlock::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class, 'floor_id');
    }
}
