<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class DeviceEvent extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'meter_device_id', 'event_type', 'status', 'error_message', 'payload'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
