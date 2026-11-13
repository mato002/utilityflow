<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceRecord extends Model
{
    protected $fillable = [
        'organization_id', 'module', 'title', 'reference', 'status', 'summary', 'amount', 'recorded_on', 'values',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'recorded_on' => 'date',
            'values' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
