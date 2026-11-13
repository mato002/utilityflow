<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeterReading extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'meter_id', 'submission_uuid', 'previous_reading', 'current_reading', 'consumption', 'reading_at', 'submitted_by', 'approved_by', 'approved_at', 'status', 'is_suspicious', 'photo_path', 'latitude', 'longitude', 'notes'];

    protected function casts(): array
    {
        return ['previous_reading' => 'decimal:4', 'current_reading' => 'decimal:4', 'consumption' => 'decimal:4', 'reading_at' => 'datetime', 'approved_at' => 'datetime', 'is_suspicious' => 'boolean'];
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    public function reader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(ReadingAudit::class);
    }

    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class, 'invoice_readings')->withPivot(['consumption', 'tariff_snapshot']);
    }

    public function utilityAllocations(): HasMany
    {
        return $this->hasMany(UtilityAllocation::class, 'meter_reading_id');
    }

    public function sharedMeterUsage(): HasMany
    {
        return $this->hasMany(UtilityAllocation::class, 'source_reading_id');
    }
}
