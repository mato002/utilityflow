<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogger
{
    public function record(
        ?int $organizationId,
        ?int $actorId,
        string $action,
        ?object $subject = null,
        ?array $before = null,
        ?array $after = null,
        ?Request $request = null,
    ): AuditLog {
        return AuditLog::create([
            'organization_id' => $organizationId,
            'actor_id' => $actorId,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'before' => $before,
            'after' => $after,
            'ip_address' => $request?->ip(),
        ]);
    }
}
