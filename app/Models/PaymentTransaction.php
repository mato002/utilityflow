<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PaymentTransaction extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'tenant_id', 'reference', 'method', 'provider', 'provider_reference', 'mpesa_receipt', 'amount', 'unallocated_amount', 'currency', 'status', 'received_at', 'provider_payload', 'recorded_by', 'submission_uuid'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'unallocated_amount' => 'decimal:2', 'received_at' => 'datetime', 'provider_payload' => 'array'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class, 'payment_allocations')->withPivot('amount')->withTimestamps();
    }
}
