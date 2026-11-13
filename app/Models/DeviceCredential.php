<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceCredential extends Model
{
    protected $fillable = ['meter_device_id', 'credential_hash', 'expires_at', 'revoked_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(MeterDevice::class, 'meter_device_id');
    }
}
