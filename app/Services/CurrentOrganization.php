<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;

class CurrentOrganization
{
    public function resolve(User $user): ?Organization
    {
        $organizationId = session('current_organization_id');

        $organization = $user->organizations()
            ->where('organizations.status', 'active')
            ->when($organizationId, fn ($query) => $query->whereKey($organizationId))
            ->first();

        if (! $organization && $organizationId) {
            session()->forget('current_organization_id');
        }

        if (! $organization) {
            $organization = $user->organizations()->where('organizations.status', 'active')->first();
            if ($organization) {
                session(['current_organization_id' => $organization->id]);
            }
        }

        return $organization;
    }
}
