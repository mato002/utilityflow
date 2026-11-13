<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PropertyBlock extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'property_id', 'name', 'code'];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function floors(): HasMany
    {
        return $this->hasMany(PropertyFloor::class, 'block_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class, 'block_id');
    }
}
