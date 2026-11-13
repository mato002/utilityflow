<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Occupancy extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'unit_id', 'tenant_id', 'move_in_date', 'move_out_date', 'status'];

    protected function casts(): array
    {
        return ['move_in_date' => 'date', 'move_out_date' => 'date'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'occupancy_members');
    }
}
