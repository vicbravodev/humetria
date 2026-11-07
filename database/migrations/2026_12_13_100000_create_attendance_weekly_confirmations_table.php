<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de jornada: the weekly conformity of each person (criterio 5.2), one row per person and week (Monday, local).
 * Only its current state: pending (silence is not conformity), confirmed or disputed with the reason, each with its moment
 * and the device whose verified signature did it. The history is in the audit trail. The hours are never stored: they are
 * always counted again from the marks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_weekly_confirmations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('employee_id');
            $table->date('week_start');
            $table->string('status', 9)->default('pending');
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('disputed_at')->nullable();
            $table->text('reason')->nullable();
            $table->uuid('device_id')->nullable();
            $table->timestampsTz();

            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
            $table->foreign(['device_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('attendance_devices')->restrictOnDelete();
            $table->unique(['employee_id', 'week_start']);
            $table->index(['tenant_id', 'week_start', 'status']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE attendance_weekly_confirmations
                ADD CONSTRAINT attendance_weekly_confirmations_week_start_monday CHECK (extract(isodow from week_start) = 1),
                ADD CONSTRAINT attendance_weekly_confirmations_status_consistent CHECK (
                    (status = 'pending' AND confirmed_at IS NULL AND disputed_at IS NULL AND reason IS NULL AND device_id IS NULL)
                    OR (status = 'confirmed' AND confirmed_at IS NOT NULL AND disputed_at IS NULL AND reason IS NULL AND device_id IS NOT NULL)
                    OR (status = 'disputed' AND confirmed_at IS NULL AND disputed_at IS NOT NULL AND btrim(reason) <> '' AND device_id IS NOT NULL)
                );
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_weekly_confirmations');
    }
};
