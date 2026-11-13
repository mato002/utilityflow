<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UtilityAllocation extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'meter_reading_id', 'source_reading_id', 'unit_id', 'tenant_id', 'policy',
        'main_consumption', 'submeter_consumption', 'allocated_consumption',
        'shared_variance', 'landlord_absorbed', 'calculation_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'main_consumption' => 'decimal:4', 'submeter_consumption' => 'decimal:4',
            'allocated_consumption' => 'decimal:4', 'shared_variance' => 'decimal:4',
            'landlord_absorbed' => 'decimal:4', 'calculation_snapshot' => 'array',
        ];
    }

    public function reading(): BelongsTo
    {
        return $this->belongsTo(MeterReading::class, 'meter_reading_id');
    }

    public function sourceReading(): BelongsTo
    {
        return $this->belongsTo(MeterReading::class, 'source_reading_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
