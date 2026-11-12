<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What a company has used of a metered resource (Humi's tokens first) in a calendar month of Mexico City. The counter is
        // what a reservation moves with one conditional UPDATE; the reservation says what is held and until when; the event is the
        // durable record of what was really used (append-only, one per key); a grant is extra quota a person gave, with the reason.
        // Nothing here ever holds what was asked or answered: only counts, ids, the period and the meter.
        Schema::create('usage_counters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('meter', 30);
            // The first day of the month, in the display time zone.
            $table->date('period_start');
            $table->unsignedBigInteger('used')->default(0);
            $table->unsignedBigInteger('reserved')->default(0);
            // The quota the last reservation of the period saw, for the history; the live one is plan plus grants.
            $table->unsignedBigInteger('quota')->nullable();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'meter', 'period_start']);
        });

        Schema::create('usage_reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('meter', 30);
            $table->date('period_start');
            $table->string('idempotency_key', 100);
            $table->unsignedBigInteger('quantity');
            $table->string('state', 10);
            // The first moment a reservation nobody settled or released is taken as dead.
            $table->timestampTz('expires_at');
            $table->timestampsTz();
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['state', 'expires_at']);
        });

        Schema::create('usage_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('meter', 30);
            $table->date('period_start');
            $table->unsignedBigInteger('quantity');
            $table->string('idempotency_key', 100);
            $table->timestampTz('occurred_at');
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'meter', 'period_start']);
        });

        Schema::create('usage_grants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('meter', 30);
            $table->date('period_start');
            $table->unsignedBigInteger('quantity');
            $table->text('reason');
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at');
            $table->index(['tenant_id', 'meter', 'period_start']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE usage_counters
                ADD CONSTRAINT usage_counters_meter_known CHECK (meter IN ('assistant_tokens')),
                ADD CONSTRAINT usage_counters_not_negative CHECK (used >= 0 AND reserved >= 0);
            ALTER TABLE usage_reservations
                ADD CONSTRAINT usage_reservations_meter_known CHECK (meter IN ('assistant_tokens')),
                ADD CONSTRAINT usage_reservations_state_known CHECK (state IN ('reserved', 'settled', 'released', 'expired')),
                ADD CONSTRAINT usage_reservations_quantity_positive CHECK (quantity > 0);
            ALTER TABLE usage_events
                ADD CONSTRAINT usage_events_meter_known CHECK (meter IN ('assistant_tokens'));
            ALTER TABLE usage_grants
                ADD CONSTRAINT usage_grants_meter_known CHECK (meter IN ('assistant_tokens')),
                ADD CONSTRAINT usage_grants_quantity_positive CHECK (quantity > 0),
                ADD CONSTRAINT usage_grants_has_reason CHECK (btrim(reason) <> '');
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION usage_events_append_only() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'usage_events is append-only';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER usage_events_no_update_delete
                BEFORE UPDATE OR DELETE ON usage_events
                FOR EACH ROW EXECUTE FUNCTION usage_events_append_only();

            CREATE TRIGGER usage_events_no_truncate
                BEFORE TRUNCATE ON usage_events
                FOR EACH STATEMENT EXECUTE FUNCTION usage_events_append_only();
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_grants');
        Schema::dropIfExists('usage_events');
        Schema::dropIfExists('usage_reservations');
        Schema::dropIfExists('usage_counters');
        DB::unprepared('DROP FUNCTION IF EXISTS usage_events_append_only()');
    }
};
