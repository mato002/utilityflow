<?php

namespace App\Http\Middleware;

use App\Services\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationContext
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user(), 401);

        $organization = app(CurrentOrganization::class)->resolve($request->user());
        abort_unless($organization, 403, 'Select an active organization to continue.');

        app()->instance('current.organization', $organization);

        return $next($request);
    }
}
