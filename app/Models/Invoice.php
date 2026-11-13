<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'billing_cycle_id', 'tenant_id', 'unit_id', 'invoice_number', 'issued_on', 'due_on', 'subtotal', 'tax_total', 'total', 'amount_paid', 'credit_total', 'currency', 'status', 'issued_at', 'created_by'];

    protected function casts(): array
    {
        return ['issued_on' => 'date', 'due_on' => 'date', 'subtotal' => 'decimal:2', 'tax_total' => 'decimal:2', 'total' => 'decimal:2', 'amount_paid' => 'decimal:2', 'credit_total' => 'decimal:2', 'issued_at' => 'datetime'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(BillingCycle::class, 'billing_cycle_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function readings(): BelongsToMany
    {
        return $this->belongsToMany(MeterReading::class, 'invoice_readings')->withPivot(['consumption', 'tariff_snapshot']);
    }

    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(PaymentTransaction::class, 'payment_allocations')->withPivot('amount')->withTimestamps();
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }
}
