<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'property_id', 'block_id', 'floor_id', 'unit_number', 'unit_type', 'floor_area', 'occupancy_status', 'notes'];

    protected function casts(): array
    {
        return ['floor_area' => 'decimal:2'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(PropertyBlock::class);
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(PropertyFloor::class, 'floor_id');
    }

    public function occupancies(): HasMany
    {
        return $this->hasMany(Occupancy::class);
    }

    public function meters(): BelongsToMany
    {
        return $this->belongsToMany(Meter::class, 'meter_assignments', 'unit_id', 'meter_id')->withPivot(['organization_id', 'property_id', 'starts_at', 'ends_at', 'assignment_type']);
    }

    public function meterAssignments(): HasMany
    {
        return $this->hasMany(MeterAssignment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
