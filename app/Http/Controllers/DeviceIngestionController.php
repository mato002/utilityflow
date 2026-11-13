<?php

namespace App\Http\Controllers;

use App\Models\DeviceEvent;
use App\Models\DeviceReading;
use App\Models\MeterDevice;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DeviceIngestionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var MeterDevice $device */
        $device = $request->attributes->get('meter_device');
        $data = $request->validate([
            'event_id' => ['required', 'string', 'max:120'],
            'value' => ['required', 'numeric', 'min:0', 'max:999999999999.9999'],
            'measured_at' => ['required', 'date'],
            'payload' => ['nullable', 'array'],
        ]);
        $measuredAt = Carbon::parse($data['measured_at']);
        if ($measuredAt->greaterThan(now()->addMinutes(5))) {
            DeviceEvent::create(['organization_id' => $device->organization_id, 'meter_device_id' => $device->id, 'event_type' => 'reading.rejected', 'status' => 'rejected', 'error_message' => 'Device timestamp is in the future.', 'payload' => $data]);

            return response()->json(['message' => 'Device timestamp is in the future.'], 422);
        }

        try {
            $reading = DeviceReading::create([
                'organization_id' => $device->organization_id,
                'meter_device_id' => $device->id,
                'event_id' => $data['event_id'],
                'value' => $data['value'],
                'measured_at' => $measuredAt,
                'payload' => $data['payload'] ?? null,
                'status' => $device->meter->readings()->where('status', 'approved')->where('reading_at', '>', $measuredAt)->exists() ? 'out_of_order' : 'pending_reconciliation',
            ]);
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                return response()->json(['message' => 'This device event has already been received.'], 409);
            }
            throw $exception;
        }

        $device->update(['last_seen_at' => now()]);
        DeviceEvent::create(['organization_id' => $device->organization_id, 'meter_device_id' => $device->id, 'event_type' => 'reading.received', 'status' => 'accepted', 'payload' => ['event_id' => $data['event_id'], 'device_reading_id' => $reading->id]]);

        return response()->json(['id' => $reading->id, 'status' => $reading->status, 'message' => 'Stored for reconciliation. It is not eligible for billing until reviewed.'], 202);
    }
}
