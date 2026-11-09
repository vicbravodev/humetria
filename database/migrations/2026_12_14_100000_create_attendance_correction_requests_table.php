<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de jornada: what a person asks RH to correct (criterio 5.4 and the design's Correcciones): a mark of theirs that
 * is wrong (`mark_id`), or one they forgot (no mark), with the time they propose, the type and why, from their verified
 * device. RH approves it (the correction becomes a new mark, `resulting_mark_id`) or rejects it with a note; it is resolved
 * once. Never a position.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_correction_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('employee_id');
            $table->uuid('mark_id')->nullable();
            $table->string('type', 11);
            $table->timestampTz('proposed_at');
            $table->text('reason');
            $table->uuid('device_id')->nullable();
            $table->string('status', 8)->default('pending');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->text('note')->nullable();
            $table->uuid('resulting_mark_id')->nullable();
            $table->timestampsTz();

            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
            $table->foreign(['mark_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('attendance_marks')->restrictOnDelete();
            $table->foreign(['resulting_mark_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('attendance_marks')->restrictOnDelete();
            $table->foreign(['device_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('attendance_devices')->restrictOnDelete();
            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index(['employee_id', 'proposed_at']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE attendance_correction_requests
                ADD CONSTRAINT attendance_correction_requests_type_known CHECK (type IN ('start', 'break_start', 'break_end', 'end')),
                ADD CONSTRAINT attendance_correction_requests_reason_present CHECK (length(btrim(reason)) > 0),
                ADD CONSTRAINT attendance_correction_requests_status_consistent CHECK (
                    (status = 'pending' AND resolved_by IS NULL AND resolved_at IS NULL AND note IS NULL AND resulting_mark_id IS NULL)
                    OR (status = 'approved' AND resolved_by IS NOT NULL AND resolved_at IS NOT NULL AND resulting_mark_id IS NOT NULL)
                    OR (status = 'rejected' AND resolved_by IS NOT NULL AND resolved_at IS NOT NULL AND length(btrim(coalesce(note, ''))) > 0 AND resulting_mark_id IS NULL)
                );

            -- One pending request per mark: a second one waits for the first to be resolved.
            CREATE UNIQUE INDEX attendance_correction_requests_one_pending_per_mark
                ON attendance_correction_requests (mark_id) WHERE status = 'pending' AND mark_id IS NOT NULL;
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_correction_requests');
    }
};
