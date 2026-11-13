<?php

namespace App\Console\Commands;

use App\Jobs\SendInvoiceReminder;
use App\Models\Invoice;
use App\Models\NotificationLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class QueueInvoiceReminders extends Command
{
    protected $signature = 'utilityflow:queue-invoice-reminders';

    protected $description = 'Queue due-soon and overdue invoice email reminders';

    public function handle(): int
    {
        $queued = 0;
        Invoice::query()
            ->with(['tenant', 'organization'])
            ->whereIn('status', ['issued', 'partial', 'overdue'])
            ->whereNotNull('due_on')
            ->whereRaw('total - amount_paid - credit_total > 0')
            ->where(function ($query): void {
                $query->whereDate('due_on', today()->addDays(3))
                    ->orWhereDate('due_on', today())
                    ->orWhereDate('due_on', '<', today());
            })
            ->orderBy('id')->chunkById(200, function ($invoices) use (&$queued): void {
                foreach ($invoices as $invoice) {
                    $email = $invoice->tenant?->email;
                    if (! $email || ! Validator::make(['email' => $email], ['email' => ['required', 'email']])->passes()) {
                        continue;
                    }

                    $daysOverdue = $invoice->due_on->diffInDays(today(), false);
                    if ($daysOverdue > 0 && $daysOverdue % 7 !== 1) {
                        continue;
                    }
                    $reminderType = $daysOverdue > 0 ? 'overdue' : 'due';
                    $templateKey = $daysOverdue > 0
                        ? 'invoice_overdue:'.$invoice->id.':'.intdiv($daysOverdue - 1, 7)
                        : 'invoice_due:'.$invoice->id.':'.$invoice->due_on->toDateString();
                    $log = NotificationLog::query()
                        ->where('organization_id', $invoice->organization_id)
                        ->where('channel', 'email')->where('recipient', $email)->where('template_key', $templateKey)->first();

                    if ($log && in_array($log->status, ['queued', 'sent'], true)) {
                        continue;
                    }
                    if ($log) {
                        $log->update(['status' => 'queued', 'error_message' => null, 'sent_at' => null]);
                    } else {
                        $log = NotificationLog::create([
                            'organization_id' => $invoice->organization_id,
                            'channel' => 'email', 'recipient' => $email, 'template_key' => $templateKey, 'status' => 'queued',
                        ]);
                    }

                    SendInvoiceReminder::dispatch($log->id, $invoice->id, $reminderType);
                    $queued++;
                }
            });

        $this->info("Queued {$queued} invoice reminder(s).");

        return self::SUCCESS;
    }
}
