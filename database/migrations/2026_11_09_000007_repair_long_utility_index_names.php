<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('billing_cycles') && ! Schema::hasIndex('billing_cycles', ['organization_id', 'property_id', 'starts_on', 'ends_on'], 'unique')) {
            Schema::table('billing_cycles', function (Blueprint $table): void {
                $table->unique(['organization_id', 'property_id', 'starts_on', 'ends_on'], 'billing_cycle_org_property_dates_unique');
            });
        }

        if (Schema::hasTable('usage_records') && ! Schema::hasIndex('usage_records', ['organization_id', 'feature_key', 'period_starts_on', 'period_ends_on'], 'unique')) {
            Schema::table('usage_records', function (Blueprint $table): void {
                $table->unique(['organization_id', 'feature_key', 'period_starts_on', 'period_ends_on'], 'usage_record_org_feature_period_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('billing_cycles') && Schema::hasIndex('billing_cycles', 'billing_cycle_org_property_dates_unique')) {
            Schema::table('billing_cycles', function (Blueprint $table): void {
                $table->dropUnique('billing_cycle_org_property_dates_unique');
            });
        }

        if (Schema::hasTable('usage_records') && Schema::hasIndex('usage_records', 'usage_record_org_feature_period_unique')) {
            Schema::table('usage_records', function (Blueprint $table): void {
                $table->dropUnique('usage_record_org_feature_period_unique');
            });
        }
    }
};
