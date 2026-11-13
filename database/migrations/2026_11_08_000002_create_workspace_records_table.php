<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('module', 48);
            $table->string('title', 180);
            $table->string('reference', 100)->nullable();
            $table->string('status', 32)->default('active');
            $table->text('summary')->nullable();
            $table->decimal('amount', 14, 2)->nullable();
            $table->date('recorded_on')->nullable();
            $table->json('values')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'module', 'status']);
            $table->unique(['organization_id', 'module', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_records');
    }
};
