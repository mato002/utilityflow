<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('meters', 'rollover_value')) {
            Schema::table('meters', function (Blueprint $table): void {
                $table->decimal('rollover_value', 16, 4)->nullable();
            });
        }
        if (! Schema::hasColumn('meter_readings', 'is_suspicious')) {
            Schema::table('meter_readings', function (Blueprint $table): void {
                $table->boolean('is_suspicious')->default(false)->after('status');
            });
        }

        $this->createIfMissing('organization_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('key', 120);
            $table->json('value')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'key']);
        });

        $this->createIfMissing('organization_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->string('token_hash', 128)->unique();
            $table->dateTime('expires_at');
            $table->dateTime('accepted_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'email']);
            $table->index(['organization_id', 'expires_at']);
        });

        $this->createIfMissing('subscription_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('key', 60)->unique();
            $table->text('description')->nullable();
            $table->decimal('monthly_price', 14, 2)->default(0);
            $table->decimal('annual_price', 14, 2)->default(0);
            $table->string('currency', 3)->default('KES');
            $table->json('limits')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $this->createIfMissing('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->string('billing_interval', 16)->default('monthly');
            $table->string('status', 24)->default('trial')->index();
            $table->dateTime('trial_ends_at')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('grace_ends_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });

        $this->createIfMissing('usage_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key', 80);
            $table->unsignedBigInteger('quantity')->default(0);
            $table->date('period_starts_on');
            $table->date('period_ends_on');
            $table->timestamps();
            $table->unique(['organization_id', 'feature_key', 'period_starts_on', 'period_ends_on'], 'usage_record_org_feature_period_unique');
        });

        $this->createIfMissing('subscription_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 80);
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'created_at']);
        });

        $this->createIfMissing('meter_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meter_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 80);
            $table->string('external_id', 120);
            $table->string('status', 24)->default('active')->index();
            $table->dateTime('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'external_id']);
            $table->index(['organization_id', 'meter_id']);
        });

        $this->createIfMissing('device_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meter_device_id')->constrained()->cascadeOnDelete();
            $table->string('credential_hash', 128)->unique();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();
        });

        $this->createIfMissing('device_readings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meter_device_id')->constrained()->cascadeOnDelete();
            $table->string('event_id', 120);
            $table->decimal('value', 16, 4);
            $table->dateTime('measured_at');
            $table->json('payload')->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->timestamps();
            $table->unique(['meter_device_id', 'event_id']);
            $table->index(['organization_id', 'measured_at']);
        });

        $this->createIfMissing('device_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meter_device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 80);
            $table->string('status', 24)->default('received');
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'created_at']);
        });

        $this->createIfMissing('notification_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->string('channel', 24);
            $table->string('subject')->nullable();
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'key', 'channel']);
        });

        $this->createIfMissing('notification_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 24);
            $table->string('recipient', 190);
            $table->string('template_key', 100)->nullable();
            $table->string('status', 24)->default('queued')->index();
            $table->text('error_message')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'created_at']);
        });

        $this->createIfMissing('report_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('report_type', 80);
            $table->json('filters')->nullable();
            $table->string('status', 24)->default('queued')->index();
            $table->string('file_path')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();
        });

        $this->createIfMissing('payment_callbacks', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 40);
            $table->string('provider_event_id', 140);
            $table->string('status', 24)->default('received')->index();
            $table->json('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_event_id']);
        });

        $this->createIfMissing('credit_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->string('credit_note_number', 80);
            $table->decimal('amount', 14, 2);
            $table->string('reason', 500);
            $table->string('status', 24)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'credit_note_number']);
        });
    }

    public function down(): void
    {
        // This baseline adopts tables that may predate Laravel migration tracking.
        // Rollback must not remove tables or columns that could contain existing data.
    }

    private function createIfMissing(string $tableName, Closure $definition): void
    {
        if (! Schema::hasTable($tableName)) {
            Schema::create($tableName, $definition);
        }
    }
};
