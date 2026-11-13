<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreditNoteService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function issue(Invoice $invoice, string $amount, string $reason, User $actor): CreditNote
    {
        return DB::transaction(function () use ($invoice, $amount, $reason, $actor): CreditNote {
            $invoice = Invoice::query()->whereKey($invoice->id)->where('organization_id', $invoice->organization_id)->lockForUpdate()->firstOrFail();
            if (! in_array($invoice->status, ['issued', 'partial', 'overdue', 'paid'], true)) {
                throw ValidationException::withMessages(['invoice' => 'Credit notes can only be issued for an issued invoice.']);
            }
            $amountCents = Money::cents($amount);
            $remainingCents = Money::cents((string) $invoice->total)
                - Money::cents((string) $invoice->amount_paid)
                - Money::cents((string) ($invoice->credit_total ?? '0.00'));
            if ($amountCents < 1 || $amountCents > $remainingCents) {
                throw ValidationException::withMessages(['amount' => 'The credit must be positive and cannot exceed the unpaid balance.']);
            }

            $credit = CreditNote::create([
                'organization_id' => $invoice->organization_id,
                'invoice_id' => $invoice->id,
                'credit_note_number' => 'CN-'.now()->format('ym').'-'.Str::upper(Str::random(8)),
                'amount' => Money::decimal($amountCents), 'reason' => $reason,
                'status' => 'issued', 'created_by' => $actor->id, 'issued_at' => now(),
            ]);
            $creditTotal = Money::cents((string) ($invoice->credit_total ?? '0.00')) + $amountCents;
            $netDue = Money::cents((string) $invoice->total) - Money::cents((string) $invoice->amount_paid) - $creditTotal;
            $invoice->update(['credit_total' => Money::decimal($creditTotal), 'status' => $netDue === 0 ? 'credited' : $invoice->status]);
            $this->audit->record($invoice->organization_id, $actor->id, 'invoice.credit_note_issued', $credit, null, [
                'invoice_id' => $invoice->id, 'credit_note_number' => $credit->credit_note_number,
                'amount' => $credit->amount, 'reason' => $credit->reason,
            ]);

            return $credit;
        });
    }
}
