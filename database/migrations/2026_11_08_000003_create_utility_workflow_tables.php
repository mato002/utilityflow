<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createIfMissing('properties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('code', 60);
            $table->string('type', 40);
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'name']);
        });

        $this->createIfMissing('property_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 40);
            $table->timestamps();
            $table->unique(['property_id', 'code']);
            $table->index(['organization_id', 'property_id']);
        });

        $this->createIfMissing('property_floors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('block_id')->nullable()->constrained('property_blocks')->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedSmallInteger('level')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'property_id']);
        });

        $this->createIfMissing('units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('block_id')->nullable()->constrained('property_blocks')->nullOnDelete();
            $table->foreignId('floor_id')->nullable()->constrained('property_floors')->nullOnDelete();
            $table->string('unit_number', 50);
            $table->string('unit_type', 40)->nullable();
            $table->string('occupancy_status', 24)->default('vacant')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['property_id', 'unit_number']);
            $table->index(['organization_id', 'property_id', 'occupancy_status']);
        });

        $this->createIfMissing('tenants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 160);
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('identification_reference', 100)->nullable();
            $table->string('emergency_contact', 160)->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();
            $table->index(['organization_id', 'name']);
            $table->index(['organization_id', 'email']);
        });

        $this->createIfMissing('occupancies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('move_in_date');
            $table->date('move_out_date')->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();
            $table->index(['organization_id', 'unit_id', 'status']);
            $table->index(['organization_id', 'tenant_id']);
        });

        $this->createIfMissing('occupancy_members', function (Blueprint $table): void {
            $table->foreignId('occupancy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->primary(['occupancy_id', 'tenant_id']);
        });

        $this->createIfMissing('meters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('meter_number', 80);
            $table->string('serial_number', 100)->nullable();
            $table->string('utility_type', 24)->index();
            $table->string('category', 40)->default('individual');
            $table->string('manufacturer', 100)->nullable();
            $table->date('installed_at')->nullable();
            $table->decimal('initial_reading', 16, 4)->default(0);
            $table->decimal('multiplier', 12, 4)->default(1);
            $table->string('status', 24)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'meter_number']);
            $table->index(['organization_id', 'utility_type', 'status']);
        });

        $this->createIfMissing('meter_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->string('assignment_type', 24)->default('individual');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'meter_id', 'ends_at']);
            $table->index(['organization_id', 'unit_id', 'ends_at']);
        });

        $this->createIfMissing('meter_readings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meter_id')->constrained()->cascadeOnDelete();
            $table->decimal('previous_reading', 16, 4);
            $table->decimal('current_reading', 16, 4);
            $table->decimal('consumption', 16, 4);
            $table->dateTime('reading_at');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->string('status', 24)->default('pending_approval')->index();
            $table->string('photo_path')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['meter_id', 'reading_at']);
            $table->index(['organization_id', 'status', 'reading_at']);
        });

        $this->createIfMissing('reading_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meter_reading_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'meter_reading_id']);
        });

        $this->createIfMissing('tariffs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('utility_type', 24)->index();
            $table->string('rate_type', 24)->default('per_unit');
            $table->unsignedInteger('version')->default(1);
            $table->decimal('fixed_charge', 14, 2)->default(0);
            $table->decimal('price_per_unit', 14, 4)->default(0);
            $table->string('currency', 3)->default('KES');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();
            $table->index(['organization_id', 'utility_type', 'effective_from']);
        });

        $this->createIfMissing('tariff_tiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tariff_id')->constrained()->cascadeOnDelete();
            $table->decimal('from_units', 16, 4)->default(0);
            $table->decimal('to_units', 16, 4)->nullable();
            $table->decimal('price_per_unit', 14, 4);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['tariff_id', 'sort_order']);
        });

        $this->createIfMissing('billing_cycles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->date('due_on')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'property_id', 'starts_on', 'ends_on'], 'billing_cycle_org_property_dates_unique');
        });

        $this->createIfMissing('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number', 80);
            $table->date('issued_on')->nullable();
            $table->date('due_on')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->string('currency', 3)->default('KES');
            $table->string('status', 24)->default('draft')->index();
            $table->dateTime('issued_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'invoice_number']);
            $table->index(['organization_id', 'tenant_id', 'status']);
        });

        $this->createIfMissing('invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description', 180);
            $table->string('utility_type', 24)->nullable();
            $table->decimal('quantity', 16, 4)->default(1);
            $table->decimal('unit_price', 14, 4)->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->json('calculation_snapshot')->nullable();
            $table->timestamps();
        });

        $this->createIfMissing('invoice_readings', function (Blueprint $table): void {
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meter_reading_id')->constrained()->restrictOnDelete();
            $table->decimal('consumption', 16, 4);
            $table->json('tariff_snapshot')->nullable();
            $table->primary(['invoice_id', 'meter_reading_id']);
        });

        $this->createIfMissing('payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference', 100);
            $table->string('method', 32);
            $table->string('provider', 40)->nullable();
            $table->string('provider_reference', 120)->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('KES');
            $table->string('status', 24)->default('pending')->index();
            $table->dateTime('received_at')->nullable();
            $table->json('provider_payload')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'reference']);
            $table->unique(['provider', 'provider_reference']);
            $table->index(['organization_id', 'tenant_id', 'status']);
        });

        $this->createIfMissing('payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamps();
            $table->unique(['payment_transaction_id', 'invoice_id']);
        });

        $this->createIfMissing('maintenance_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('meter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('category', 40)->default('other');
            $table->string('priority', 16)->default('normal')->index();
            $table->string('status', 24)->default('open')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'priority']);
        });

        $this->createIfMissing('maintenance_updates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('maintenance_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 24)->nullable();
            $table->text('body');
            $table->timestamps();
        });

        $this->createIfMissing('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->string('subject_type', 120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        // This baseline adopts tables that may predate Laravel migration tracking.
        // Dropping them here could destroy application data owned by the existing schema.
    }

    private function createIfMissing(string $tableName, Closure $definition): void
    {
        if (! Schema::hasTable($tableName)) {
            Schema::create($tableName, $definition);
        }
    }
};
