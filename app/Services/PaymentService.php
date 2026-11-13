<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PaymentTransaction;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<int, array{invoice_id:int, amount:string}> $allocations */
    public function record(
        int $organizationId,
        Tenant $tenant,
        string $reference,
        string $method,
        string $amount,
        array $allocations,
        User $actor,
        ?string $provider = null,
        ?string $providerReference = null,
        ?string $submissionUuid = null,
    ): PaymentTransaction {
        return DB::transaction(function () use ($organizationId, $tenant, $reference, $method, $amount, $allocations, $actor, $provider, $providerReference, $submissionUuid): PaymentTransaction {
            $tenant = Tenant::query()->whereKey($tenant->id)->where('organization_id', $organizationId)->firstOrFail();
            if ($submissionUuid) {
                $existing = PaymentTransaction::query()->where('organization_id', $organizationId)->where('submission_uuid', $submissionUuid)->first();
                if ($existing) {
                    return $existing->fresh(['invoices']);
                }
            }
            $amountCents = Money::cents($amount);
            if ($amountCents < 1) {
                throw ValidationException::withMessages(['amount' => 'Enter a positive payment amount.']);
            }

            if (PaymentTransaction::where('organization_id', $organizationId)->where('reference', $reference)->exists()) {
                throw ValidationException::withMessages(['reference' => 'This payment reference has already been recorded.']);
            }
            if ($provider && $providerReference && PaymentTransaction::where('provider', $provider)->where('provider_reference', $providerReference)->exists()) {
                throw ValidationException::withMessages(['provider_reference' => 'This provider transaction has already been processed.']);
            }

            $invoiceIds = array_map(fn (array $allocation): int => (int) $allocation['invoice_id'], $allocations);
            if (count($invoiceIds) !== count(array_unique($invoiceIds))) {
                throw ValidationException::withMessages(['allocations' => 'Each invoice may only appear once in a payment allocation.']);
            }

            $payment = PaymentTransaction::create([
                'organization_id' => $organizationId,
                'tenant_id' => $tenant->id,
                'reference' => $reference,
                'method' => $method,
                'provider' => $provider,
                'provider_reference' => $providerReference,
                'amount' => Money::decimal($amountCents),
                'unallocated_amount' => Money::decimal($amountCents),
                'currency' => 'KES',
                'status' => 'received',
                'received_at' => now(),
                'recorded_by' => $actor->id,
                'submission_uuid' => $submissionUuid,
            ]);

            $allocatedCents = $this->allocate($payment, $allocations);
            if ($allocatedCents > $amountCents) {
                throw ValidationException::withMessages(['allocations' => 'Invoice allocations cannot exceed the payment amount.']);
            }
            $payment->update(['unallocated_amount' => Money::decimal($amountCents - $allocatedCents)]);

            $this->audit->record($organizationId, $actor->id, 'payment.recorded', $payment, null, $payment->only(['reference', 'amount', 'unallocated_amount', 'method', 'status']));

            return $payment->fresh(['invoices']);
        });
    }

    public function confirmDaraja(PaymentTransaction $payment, string $receipt): PaymentTransaction
    {
        return DB::transaction(function () use ($payment, $receipt): PaymentTransaction {
            $payment = PaymentTransaction::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->provider !== 'daraja' || $payment->status !== 'pending') {
                return $payment->fresh(['invoices']);
            }

            if (PaymentTransaction::query()->where('mpesa_receipt', $receipt)->where('id', '!=', $payment->id)->exists()) {
                $payment->update(['status' => 'review', 'provider_payload' => array_merge($payment->provider_payload ?? [], ['callback_receipt' => $receipt, 'review_reason' => 'Duplicate M-Pesa receipt number.'])]);

                return $payment->fresh(['invoices']);
            }

            $payment->update([
                'mpesa_receipt' => $receipt,
                'provider_payload' => array_merge($payment->provider_payload ?? [], ['mpesa_receipt' => $receipt, 'callback_received_at' => now()->toIso8601String()]),
                'status' => 'received',
                'received_at' => now(),
            ]);

            $invoiceId = data_get($payment->provider_payload, 'invoice_id');
            $allocatedCents = 0;
            if ($invoiceId) {
                $invoice = Invoice::query()->whereKey($invoiceId)->where('organization_id', $payment->organization_id)->where('tenant_id', $payment->tenant_id)->lockForUpdate()->first();
                if ($invoice && in_array($invoice->status, ['issued', 'partial', 'overdue'], true)) {
                    $outstandingCents = Money::cents((string) $invoice->total) - Money::cents((string) $invoice->amount_paid) - Money::cents((string) ($invoice->credit_total ?? '0.00'));
                    if ($outstandingCents > 0) {
                        $allocatedCents = $this->allocate($payment, [[
                            'invoice_id' => $invoice->id,
                            'amount' => Money::decimal(min(Money::cents((string) $payment->amount), $outstandingCents)),
                        ]]);
                    }
                }
            }
            $payment->update(['unallocated_amount' => Money::decimal(Money::cents((string) $payment->amount) - $allocatedCents)]);
            $this->audit->record($payment->organization_id, null, 'payment.daraja_confirmed', $payment, ['status' => 'pending'], ['status' => 'received', 'reference' => $receipt]);

            return $payment->fresh(['invoices']);
        });
    }

    public function resolveDarajaReview(PaymentTransaction $payment, string $receipt, string $amount, User $actor): PaymentTransaction
    {
        return DB::transaction(function () use ($payment, $receipt, $amount, $actor): PaymentTransaction {
            $payment = PaymentTransaction::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->provider !== 'daraja' || $payment->status !== 'review') {
                throw ValidationException::withMessages(['payment' => 'Only M-Pesa payments needing review can be reconciled.']);
            }

            if (PaymentTransaction::query()->where('mpesa_receipt', $receipt)->where('id', '!=', $payment->id)->exists()) {
                throw ValidationException::withMessages(['receipt' => 'This M-Pesa receipt is already attached to another payment.']);
            }

            $amountCents = Money::cents($amount);
            if ($amountCents < 1) {
                throw ValidationException::withMessages(['amount' => 'Enter the confirmed amount received.']);
            }

            $before = ['status' => $payment->status, 'amount' => $payment->amount];
            $payment->update([
                'mpesa_receipt' => $receipt,
                'amount' => Money::decimal($amountCents),
                'unallocated_amount' => Money::decimal($amountCents),
                'status' => 'received',
                'received_at' => now(),
                'recorded_by' => $actor->id,
                'provider_payload' => array_merge($payment->provider_payload ?? [], ['review_resolved_by' => $actor->id, 'review_resolved_at' => now()->toIso8601String()]),
            ]);

            $allocatedCents = 0;
            $invoiceId = data_get($payment->provider_payload, 'invoice_id');
            if ($invoiceId) {
                $invoice = Invoice::query()->whereKey($invoiceId)->where('organization_id', $payment->organization_id)->where('tenant_id', $payment->tenant_id)->lockForUpdate()->first();
                if ($invoice && in_array($invoice->status, ['issued', 'partial', 'overdue'], true)) {
                    $outstandingCents = Money::cents((string) $invoice->total) - Money::cents((string) $invoice->amount_paid) - Money::cents((string) ($invoice->credit_total ?? '0.00'));
                    if ($outstandingCents > 0) {
                        $allocatedCents = $this->allocate($payment, [[
                            'invoice_id' => $invoice->id,
                            'amount' => Money::decimal(min($amountCents, $outstandingCents)),
                        ]]);
                    }
                }
            }

            $payment->update(['unallocated_amount' => Money::decimal($amountCents - $allocatedCents)]);
            $this->audit->record($payment->organization_id, $actor->id, 'payment.daraja_review_resolved', $payment, $before, ['status' => 'received', 'amount' => $payment->amount, 'mpesa_receipt' => $receipt], request());

            return $payment->fresh(['invoices']);
        });
    }

    /** @param array<int, array{invoice_id:int, amount:string}> $allocations */
    public function applyUnallocated(PaymentTransaction $payment, array $allocations, User $actor): PaymentTransaction
    {
        return DB::transaction(function () use ($payment, $allocations, $actor): PaymentTransaction {
            $payment = PaymentTransaction::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->status !== 'received' || Money::cents((string) $payment->unallocated_amount) < 1) {
                throw ValidationException::withMessages(['payment' => 'This payment has no available unallocated balance.']);
            }

            $before = ['unallocated_amount' => $payment->unallocated_amount];
            $allocatedCents = $this->allocate($payment, $allocations);
            $unallocatedCents = Money::cents((string) $payment->unallocated_amount);
            if ($allocatedCents > $unallocatedCents) {
                throw ValidationException::withMessages(['allocations' => 'Allocations cannot exceed the unallocated payment balance.']);
            }
            $payment->update(['unallocated_amount' => Money::decimal($unallocatedCents - $allocatedCents)]);
            $this->audit->record($payment->organization_id, $actor->id, 'payment.credit_applied', $payment, $before, ['unallocated_amount' => $payment->unallocated_amount], request());

            return $payment->fresh(['invoices']);
        });
    }

    /** @param array<int, array{invoice_id:int, amount:string}> $allocations */
    private function allocate(PaymentTransaction $payment, array $allocations): int
    {
        if ($allocations === []) {
            return 0;
        }

        $invoiceIds = array_map(fn (array $allocation): int => (int) $allocation['invoice_id'], $allocations);
        if (count($invoiceIds) !== count(array_unique($invoiceIds))) {
            throw ValidationException::withMessages(['allocations' => 'Each invoice may only appear once in a single allocation.']);
        }

        $allocatedCents = 0;
        foreach ($allocations as $allocation) {
            $invoice = Invoice::query()->whereKey($allocation['invoice_id'])
                ->where('organization_id', $payment->organization_id)->where('tenant_id', $payment->tenant_id)->lockForUpdate()->firstOrFail();
            if (! in_array($invoice->status, ['issued', 'partial', 'overdue'], true)) {
                throw ValidationException::withMessages(['allocations' => 'Payments can only be applied to issued invoices.']);
            }

            $allocationCents = Money::cents((string) $allocation['amount']);
            $outstandingCents = Money::cents((string) $invoice->total) - Money::cents((string) $invoice->amount_paid) - Money::cents((string) ($invoice->credit_total ?? '0.00'));
            if ($allocationCents < 1 || $allocationCents > $outstandingCents) {
                throw ValidationException::withMessages(['allocations' => 'An allocation must be positive and cannot exceed the invoice balance.']);
            }

            $existingAllocation = $payment->invoices()->where('invoices.id', $invoice->id)->first();
            if ($existingAllocation) {
                $pivotCents = Money::cents((string) $existingAllocation->pivot->amount) + $allocationCents;
                $payment->invoices()->updateExistingPivot($invoice->id, ['amount' => Money::decimal($pivotCents)]);
            } else {
                $payment->invoices()->attach($invoice->id, ['amount' => Money::decimal($allocationCents)]);
            }
            $allocatedCents += $allocationCents;
            $paidCents = Money::cents((string) $invoice->amount_paid) + $allocationCents;
            $creditCents = Money::cents((string) ($invoice->credit_total ?? '0.00'));
            $totalCents = Money::cents((string) $invoice->total);
            $status = $paidCents + $creditCents >= $totalCents ? ($creditCents >= $totalCents ? 'credited' : 'paid') : 'partial';
            $invoice->update(['amount_paid' => Money::decimal($paidCents), 'status' => $status]);
        }

        return $allocatedCents;
    }

    public function reverse(PaymentTransaction $payment, User $actor, string $reason): PaymentTransaction
    {
        return DB::transaction(function () use ($payment, $actor, $reason): PaymentTransaction {
            $payment = PaymentTransaction::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->status !== 'received') {
                throw ValidationException::withMessages(['payment' => 'Only received payments can be reversed.']);
            }

            foreach ($payment->invoices as $allocatedInvoice) {
                $invoice = Invoice::query()->whereKey($allocatedInvoice->id)->lockForUpdate()->firstOrFail();
                $paidCents = Money::cents((string) $invoice->amount_paid) - Money::cents((string) $allocatedInvoice->pivot->amount);
                $creditCents = Money::cents((string) ($invoice->credit_total ?? '0.00'));
                $totalCents = Money::cents((string) $invoice->total);
                $status = $paidCents + $creditCents >= $totalCents
                    ? ($creditCents >= $totalCents ? 'credited' : 'paid')
                    : ($paidCents > 0 ? 'partial' : (($invoice->due_on && $invoice->due_on->lt(today())) ? 'overdue' : 'issued'));
                $invoice->update([
                    'amount_paid' => Money::decimal(max(0, $paidCents)),
                    'status' => $status,
                ]);
            }

            $before = $payment->only(['status']);
            $payment->update(['status' => 'reversed']);
            $this->audit->record($payment->organization_id, $actor->id, 'payment.reversed', $payment, $before, ['status' => 'reversed', 'reason' => $reason]);

            return $payment->fresh(['invoices']);
        });
    }
}
