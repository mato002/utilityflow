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
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('daraja_environment', 20)->nullable();
            $table->text('daraja_consumer_key')->nullable();
            $table->text('daraja_consumer_secret')->nullable();
            $table->string('daraja_shortcode', 32)->nullable();
            $table->text('daraja_passkey')->nullable();
            $table->string('daraja_transaction_type', 40)->nullable();
        });
        Schema::table('organizations', function (Blueprint $table) {
            //
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn([
                'daraja_environment',
                'daraja_consumer_key',
                'daraja_consumer_secret',
                'daraja_shortcode',
                'daraja_passkey',
                'daraja_transaction_type',
            ]);
        });
        Schema::table('organizations', function (Blueprint $table) {
            //
        });
    }
};
