<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payment_transactions', 'unallocated_amount')) {
            Schema::table('payment_transactions', function (Blueprint $table): void {
                $table->decimal('unallocated_amount', 14, 2)->default(0)->after('amount');
            });
        }

        DB::table('payment_transactions')->orderBy('id')->chunkById(200, function ($payments): void {
            foreach ($payments as $payment) {
                $allocated = (float) DB::table('payment_allocations')->where('payment_transaction_id', $payment->id)->sum('amount');
                DB::table('payment_transactions')->where('id', $payment->id)->update([
                    'unallocated_amount' => max(0, (float) $payment->amount - $allocated),
                ]);
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('payment_transactions', 'unallocated_amount')) {
            Schema::table('payment_transactions', function (Blueprint $table): void {
                $table->dropColumn('unallocated_amount');
            });
        }
    }
};
