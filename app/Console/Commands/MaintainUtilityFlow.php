<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Console\Command;

class MaintainUtilityFlow extends Command
{
    protected $signature = 'utilityflow:maintain';

    protected $description = 'Update overdue invoices and subscription lifecycle statuses';

    public function handle(): int
    {
        $overdue = Invoice::query()
            ->whereIn('status', ['issued', 'partial'])
            ->whereNotNull('due_on')
            ->whereDate('due_on', '<', today())
            ->update(['status' => 'overdue', 'updated_at' => now()]);

        $expired = Subscription::query()
            ->where(function ($query): void {
                $query->where(fn ($trial) => $trial->where('status', 'trial')->whereNotNull('trial_ends_at')->where('trial_ends_at', '<=', now()))
                    ->orWhere(fn ($active) => $active->where('status', 'active')->whereNotNull('ends_at')->where('ends_at', '<=', now()));
            })
            ->update(['status' => 'past_due', 'grace_ends_at' => now()->addDays(7), 'updated_at' => now()]);

        $suspended = Subscription::query()
            ->where('status', 'past_due')
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '<=', now())
            ->update(['status' => 'suspended', 'updated_at' => now()]);

        $this->info("Marked {$overdue} invoices overdue; {$expired} subscriptions past due; {$suspended} subscriptions suspended.");

        return self::SUCCESS;
    }
}
