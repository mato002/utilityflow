<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utility_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meter_reading_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_reading_id')->nullable()->constrained('meter_readings')->nullOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('policy', 32);
            $table->decimal('main_consumption', 16, 4);
            $table->decimal('submeter_consumption', 16, 4)->default(0);
            $table->decimal('allocated_consumption', 16, 4);
            $table->decimal('shared_variance', 16, 4)->default(0);
            $table->decimal('landlord_absorbed', 16, 4)->default(0);
            $table->json('calculation_snapshot')->nullable();
            $table->timestamps();
            $table->unique(['meter_reading_id', 'unit_id']);
            $table->index(['organization_id', 'policy']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utility_allocations');
    }
};
