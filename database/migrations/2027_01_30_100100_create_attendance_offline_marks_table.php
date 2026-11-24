<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de jornada: each mark delivered after being made without a connection, with a valid ticket and signature,
 * accepted or rejected (criterios 3.3.1 and 7.1). One row per ticket. Insert-only like the marks (criterio 5.4), with the
 * demo company's exception: resetting it wipes everything it holds (2027_01_19_100000_let_the_demo_company_be_wiped).
 * The ticket, an invalid signature and a malformed element are answered without a row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_offline_marks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('employee_id');
            $table->string('outcome', 8);
            $table->string('reason', 24)->nullable();
            $table->uuid('mark_id')->nullable()->unique();
            $table->uuid('work_center_id');
            $table->uuid('away_center_id')->nullable();
            $table->string('mark_type', 11);
            $table->string('time_basis', 17)->nullable();
            $table->timestampTz('declared_at', 0);
            $table->timestampTz('counted_at', 6)->nullable();
            $table->timestampTz('window_start', 0)->nullable();
            $table->string('ticket_nonce', 32);
            $table->timestampTz('ticket_issued_at', 0);
            $table->timestampTz('received_at', 6);
            $table->jsonb('review_flags')->default('[]');
            $table->timestampTz('device_clock_at', 0)->nullable();
            $table->string('signer_type', 10);
            $table->uuid('signer_id');
            $table->unsignedInteger('seq');
            $table->string('justification', 500)->nullable();
            $table->unsignedInteger('agreement_version');
            $table->char('challenge_hash', 64);
            $table->char('assertion_hash', 64);
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
            $table->foreign(['work_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
            $table->foreign(['away_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
            $table->foreign(['mark_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('attendance_marks')->restrictOnDelete();
            $table->unique(['tenant_id', 'ticket_nonce']);
            $table->unique(['id', 'tenant_id'], 'attendance_offline_marks_id_tenant_unique');
            $table->index(['tenant_id', 'employee_id', 'received_at'], 'attendance_offline_marks_employee_received_index');
            $table->index(['tenant_id', 'outcome', 'created_at'], 'attendance_offline_marks_outcome_created_index');
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE attendance_offline_marks
                ADD CONSTRAINT attendance_offline_marks_outcome_known CHECK (outcome IN ('accepted', 'rejected')),
                ADD CONSTRAINT attendance_offline_marks_accepted_complete CHECK (
                    (outcome = 'accepted') = (mark_id IS NOT NULL AND reason IS NULL AND counted_at IS NOT NULL AND time_basis IS NOT NULL)
                ),
                ADD CONSTRAINT attendance_offline_marks_rejected_has_reason CHECK ((outcome = 'rejected') = (reason IS NOT NULL)),
                ADD CONSTRAINT attendance_offline_marks_reason_known CHECK (reason IN ('ticket_expired', 'person_inactive', 'agreement_not_accepted', 'sequence_conflict', 'wrong_center', 'pass_expired')),
                ADD CONSTRAINT attendance_offline_marks_signer_known CHECK (signer_type IN ('passkey', 'device_key')),
                ADD CONSTRAINT attendance_offline_marks_type_known CHECK (mark_type IN ('start', 'break_start', 'break_end', 'end')),
                ADD CONSTRAINT attendance_offline_marks_basis_known CHECK (time_basis IN ('qr_window', 'declared_interval')),
                ADD CONSTRAINT attendance_offline_marks_window_has_start CHECK (time_basis IS DISTINCT FROM 'qr_window' OR window_start IS NOT NULL),
                ADD CONSTRAINT attendance_offline_marks_counted_between CHECK (counted_at IS NULL OR (counted_at >= ticket_issued_at AND counted_at <= received_at)),
                ADD CONSTRAINT attendance_offline_marks_flags_known CHECK (
                    jsonb_typeof(review_flags) = 'array'
                    AND review_flags <@ '["wide_interval", "old_window", "no_position", "outside_zone", "declared_adjusted"]'::jsonb
                ),
                ADD CONSTRAINT attendance_offline_marks_seq_not_negative CHECK (seq >= 0);

            CREATE OR REPLACE FUNCTION attendance_offline_marks_append_only() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    IF EXISTS (SELECT 1 FROM tenants WHERE id = OLD.tenant_id AND is_demo) THEN
                        RETURN OLD;
                    END IF;
                END IF;

                RAISE EXCEPTION 'attendance_offline_marks is append-only';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER attendance_offline_marks_no_update_delete
                BEFORE UPDATE OR DELETE ON attendance_offline_marks
                FOR EACH ROW EXECUTE FUNCTION attendance_offline_marks_append_only();

            CREATE TRIGGER attendance_offline_marks_no_truncate
                BEFORE TRUNCATE ON attendance_offline_marks
                FOR EACH STATEMENT EXECUTE FUNCTION attendance_offline_marks_append_only();
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_offline_marks');
        DB::unprepared('DROP FUNCTION IF EXISTS attendance_offline_marks_append_only()');
    }
};
