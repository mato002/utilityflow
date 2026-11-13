<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNote extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'invoice_id', 'credit_note_number', 'amount', 'reason', 'status', 'created_by', 'issued_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'issued_at' => 'datetime'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
