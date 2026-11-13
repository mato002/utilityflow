<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tariffs', function (Blueprint $table): void {
            $table->foreignId('meter_id')->nullable()->after('property_id')->constrained()->nullOnDelete();
            $table->index(['organization_id', 'meter_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::table('tariffs', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'meter_id', 'effective_from']);
            $table->dropConstrainedForeignId('meter_id');
        });
    }
};
