<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table): void {
            $table->uuid('submission_uuid')->nullable()->after('recorded_by');
            $table->string('mpesa_receipt', 32)->nullable()->after('submission_uuid')->unique();
            $table->unique(['organization_id', 'submission_uuid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table): void {
            $table->dropUnique(['organization_id', 'submission_uuid']);
            $table->dropUnique(['mpesa_receipt']);
            $table->dropColumn(['submission_uuid', 'mpesa_receipt']);
        });
    }
};
