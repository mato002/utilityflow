<?php

namespace App\Jobs;

use App\Mail\InvoiceReminderMail;
use App\Models\Invoice;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class SendInvoiceReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(public int $notificationLogId, public int $invoiceId, public string $reminderType) {}

    public function handle(): void
    {
        $log = NotificationLog::query()->findOrFail($this->notificationLogId);
        $invoice = Invoice::query()->with(['tenant', 'organization'])->findOrFail($this->invoiceId);
        $outstanding = (float) $invoice->total - (float) $invoice->amount_paid - (float) $invoice->credit_total;
        if (! $invoice->tenant?->email || strcasecmp($invoice->tenant->email, $log->recipient) !== 0 || $outstanding <= 0 || ! in_array($invoice->status, ['issued', 'partial', 'overdue'], true)) {
            $log->update(['status' => 'skipped', 'error_message' => null]);

            return;
        }
        $templateKey = $this->reminderType === 'overdue' ? 'invoice_overdue' : 'invoice_due_reminder';
        $template = NotificationTemplate::query()
            ->where('channel', 'email')
            ->where('key', $templateKey)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $invoice->organization_id))
            ->orderByRaw('organization_id IS NULL')
            ->first();

        $portalUrl = route('login');
        $replacements = [
            '{{organization}}' => $invoice->organization->name,
            '{{recipient}}' => $invoice->tenant->name,
            '{{invoice_number}}' => $invoice->invoice_number,
            '{{amount}}' => $invoice->currency.' '.number_format(max(0, $outstanding), 2),
            '{{due_date}}' => $invoice->due_on?->format('j M Y') ?? '—',
            '{{portal_url}}' => $portalUrl,
        ];
        $subject = strtr($template?->subject ?: ($this->reminderType === 'overdue' ? 'Overdue utility invoice' : 'Upcoming utility invoice due'), $replacements);
        $body = strtr($template?->body ?: 'Hello {{recipient}}, invoice {{invoice_number}} from {{organization}} has an outstanding balance of {{amount}}, due {{due_date}}. Sign in to your resident portal: {{portal_url}}', $replacements);

        Mail::to($log->recipient)->send(new InvoiceReminderMail($invoice, $subject, $body));
        $log->update(['status' => 'sent', 'sent_at' => now(), 'error_message' => null]);
    }

    public function failed(?Throwable $exception): void
    {
        NotificationLog::query()->whereKey($this->notificationLogId)->update([
            'status' => 'failed',
            'error_message' => Str::limit($exception?->getMessage() ?? 'Invoice reminder delivery failed.', 1000, ''),
            'updated_at' => now(),
        ]);
    }
}
