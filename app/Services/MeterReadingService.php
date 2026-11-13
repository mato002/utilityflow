<?php

namespace App\Services;

use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\ReadingAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MeterReadingService
{
    public function submit(Meter $meter, User $reader, float $current, string $readingAt, ?string $photoPath = null, ?string $notes = null, ?string $submissionUuid = null): MeterReading
    {
        return DB::transaction(function () use ($meter, $reader, $current, $readingAt, $photoPath, $notes, $submissionUuid): MeterReading {
            $meter = Meter::query()->whereKey($meter->id)->where('organization_id', $meter->organization_id)->lockForUpdate()->firstOrFail();
            if ($submissionUuid) {
                $existing = MeterReading::query()->where('organization_id', $meter->organization_id)->where('submission_uuid', $submissionUuid)->first();
                if ($existing) {
                    return $existing;
                }
            }
            if ($meter->status !== 'active') {
                throw ValidationException::withMessages(['meter_id' => 'Readings can only be recorded for active meters.']);
            }

            $exists = MeterReading::where('meter_id', $meter->id)->where('reading_at', $readingAt)->exists();
            if ($exists) {
                throw ValidationException::withMessages(['reading_at' => 'A reading already exists for this meter and date.']);
            }

            $lastReading = MeterReading::where('meter_id', $meter->id)->where('status', 'approved')->latest('reading_at')->first();
            $previous = (float) ($lastReading?->current_reading ?? $meter->initial_reading);
            if ($current < 0) {
                throw ValidationException::withMessages(['current_reading' => 'The reading cannot be negative.']);
            }
            if ($current < $previous && $meter->rollover_value === null) {
                throw ValidationException::withMessages(['current_reading' => 'This value is lower than the last approved reading. Configure the meter rollover value before submitting it.']);
            }
            if ($current < $previous && (float) $meter->rollover_value <= $previous) {
                throw ValidationException::withMessages(['current_reading' => 'The configured rollover value must be greater than the previous reading.']);
            }

            $rawConsumption = $current >= $previous
                ? $current - $previous
                : ((float) $meter->rollover_value - $previous) + $current;
            $consumption = round($rawConsumption * (float) $meter->multiplier, 4);
            $isSuspicious = $consumption > max(1000, $previous * 3);
            $reading = MeterReading::create([
                'organization_id' => $meter->organization_id,
                'meter_id' => $meter->id,
                'submission_uuid' => $submissionUuid,
                'previous_reading' => $previous,
                'current_reading' => $current,
                'consumption' => $consumption,
                'reading_at' => $readingAt,
                'submitted_by' => $reader->id,
                'status' => 'pending_approval',
                'is_suspicious' => $isSuspicious,
                'photo_path' => $photoPath,
                'notes' => $notes,
            ]);

            ReadingAudit::create([
                'organization_id' => $meter->organization_id,
                'meter_reading_id' => $reading->id,
                'actor_id' => $reader->id,
                'action' => 'submitted',
                'after' => $reading->only(['previous_reading', 'current_reading', 'consumption', 'reading_at', 'status', 'is_suspicious']),
            ]);

            return $reading;
        });
    }

    public function approve(MeterReading $reading, User $approver): MeterReading
    {
        return DB::transaction(function () use ($reading, $approver): MeterReading {
            $reading = MeterReading::query()->whereKey($reading->id)->lockForUpdate()->firstOrFail();
            if ($reading->status !== 'pending_approval') {
                throw ValidationException::withMessages(['reading' => 'Only pending readings can be approved.']);
            }

            $before = $reading->only(['status', 'approved_by', 'approved_at']);
            $reading->update(['status' => 'approved', 'approved_by' => $approver->id, 'approved_at' => now()]);
            ReadingAudit::create([
                'organization_id' => $reading->organization_id,
                'meter_reading_id' => $reading->id,
                'actor_id' => $approver->id,
                'action' => 'approved',
                'before' => $before,
                'after' => $reading->only(['status', 'approved_by', 'approved_at']),
            ]);

            return $reading;
        });
    }

    public function reject(MeterReading $reading, User $reviewer, string $reason): MeterReading
    {
        return DB::transaction(function () use ($reading, $reviewer, $reason): MeterReading {
            $reading = MeterReading::query()->whereKey($reading->id)->lockForUpdate()->firstOrFail();
            if ($reading->status !== 'pending_approval') {
                throw ValidationException::withMessages(['reading' => 'Only pending readings can be rejected.']);
            }

            $before = $reading->only(['status', 'notes']);
            $reading->update(['status' => 'rejected', 'notes' => trim(($reading->notes ? $reading->notes."\n" : '').'Review: '.$reason)]);
            ReadingAudit::create([
                'organization_id' => $reading->organization_id,
                'meter_reading_id' => $reading->id,
                'actor_id' => $reviewer->id,
                'action' => 'rejected',
                'before' => $before,
                'after' => $reading->only(['status', 'notes']),
            ]);

            return $reading;
        });
    }
}
