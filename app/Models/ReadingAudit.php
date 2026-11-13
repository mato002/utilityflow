<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingAudit extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'meter_reading_id', 'actor_id', 'action', 'before', 'after'];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }

    public function reading(): BelongsTo
    {
        return $this->belongsTo(MeterReading::class, 'meter_reading_id');
    }
}
