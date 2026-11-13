<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Meter extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'meter_number', 'serial_number', 'utility_type', 'category', 'manufacturer', 'installed_at', 'initial_reading', 'multiplier', 'rollover_value', 'status', 'notes'];

    protected function casts(): array
    {
        return ['installed_at' => 'date', 'initial_reading' => 'decimal:4', 'multiplier' => 'decimal:4', 'rollover_value' => 'decimal:4'];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(MeterAssignment::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(MeterReading::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(MeterDevice::class);
    }

    public function activeAssignment(): HasMany
    {
        return $this->assignments()->whereNull('ends_at');
    }
}
