<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'is_platform_admin', 'color_palette'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
        ];
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->withPivot(['role_id', 'status', 'joined_at'])
            ->wherePivot('status', 'active')
            ->withTimestamps();
    }

    public function tenantAccounts(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function hasOrganizationPermission(Organization $organization, string $permission): bool
    {
        $membership = $this->organizations()
            ->where('organizations.id', $organization->id)
            ->first()?->pivot;

        if (! $membership) {
            return false;
        }

        $role = Role::query()->whereKey($membership->role_id)
            ->where('organization_id', $organization->id)
            ->first();

        return $role && ($role->key === 'owner' || $role->permissions()->where('key', $permission)->exists());
    }
}
