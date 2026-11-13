<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceReading extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'meter_device_id', 'event_id', 'value', 'measured_at', 'payload', 'status'];

    protected function casts(): array
    {
        return ['value' => 'decimal:4', 'measured_at' => 'datetime', 'payload' => 'array'];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(MeterDevice::class, 'meter_device_id');
    }
}
