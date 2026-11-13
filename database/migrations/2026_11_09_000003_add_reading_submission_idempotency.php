<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meter_readings', function (Blueprint $table): void {
            $table->uuid('submission_uuid')->nullable()->after('meter_id');
            $table->unique(['organization_id', 'submission_uuid']);
        });
    }

    public function down(): void
    {
        Schema::table('meter_readings', function (Blueprint $table): void {
            $table->dropUnique(['organization_id', 'submission_uuid']);
            $table->dropColumn('submission_uuid');
        });
    }
};
