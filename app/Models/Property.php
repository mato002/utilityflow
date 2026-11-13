<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'name', 'code', 'type', 'address', 'city', 'description', 'status', 'manager_id'];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(PropertyBlock::class);
    }

    public function floors(): HasMany
    {
        return $this->hasMany(PropertyFloor::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function meters(): BelongsToMany
    {
        return $this->belongsToMany(Meter::class, 'meter_assignments')->withPivot(['organization_id', 'unit_id', 'starts_at', 'ends_at', 'assignment_type']);
    }

    public function meterAssignments(): HasMany
    {
        return $this->hasMany(MeterAssignment::class);
    }
}
