<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->decimal('credit_total', 14, 2)->default(0)->after('amount_paid');
        });

        if (! Schema::hasTable('credit_notes')) {
            Schema::create('credit_notes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
                $table->string('credit_note_number', 80);
                $table->decimal('amount', 14, 2);
                $table->string('reason', 500);
                $table->string('status', 24)->default('issued')->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('issued_at');
                $table->timestamps();
                $table->unique(['organization_id', 'credit_note_number']);
                $table->index(['organization_id', 'invoice_id', 'status']);
            });
        } else {
            Schema::table('credit_notes', fn (Blueprint $table) => $table->timestamp('issued_at')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('credit_notes')) {
            $issuedAt = collect(Schema::getColumns('credit_notes'))->firstWhere('name', 'issued_at');
            if ($issuedAt && ! $issuedAt['nullable']) {
                Schema::dropIfExists('credit_notes');
            } elseif ($issuedAt) {
                Schema::table('credit_notes', fn (Blueprint $table) => $table->dropColumn('issued_at'));
            }
        }
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn('credit_total'));
    }
};
