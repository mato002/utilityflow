<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', fn (Blueprint $table) => $table->decimal('floor_area', 12, 2)->nullable()->after('unit_type'));
    }

    public function down(): void
    {
        Schema::table('units', fn (Blueprint $table) => $table->dropColumn('floor_area'));
    }
};
