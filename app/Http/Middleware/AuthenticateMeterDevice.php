<?php

namespace App\Http\Middleware;

use App\Models\DeviceCredential;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMeterDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        abort_unless($token, 401, 'A meter device bearer token is required.');
        $credential = DeviceCredential::with('device')
            ->where('credential_hash', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();
        abort_unless($credential && $credential->device?->status === 'active', 401, 'The device credential is invalid or expired.');

        $request->attributes->set('meter_device', $credential->device);

        return $next($request);
    }
}
