<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vacaciones: what a person asks for, vacations (no type) or a leave that uses no balance (criterios C-04, C-17, C-18 and
 * C-19). The working days are counted when it is made and never again. With it the ledger gets the foreign key it was
 * waiting for: an entry names a request of the same person, a request charges each tranche of a period once, and the days
 * a cancelled request gives back need no note of RH.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('employee_id');
            $table->uuid('leave_type_id')->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->smallInteger('working_days');
            $table->string('status', 9);
            $table->text('note')->nullable();
            $table->boolean('had_balance')->nullable();
            $table->jsonb('allocation')->default('[]');
            $table->smallInteger('short_by')->default(0);
            $table->uuid('approver_employee_id')->nullable();
            $table->timestampTz('escalated_at')->nullable();
            $table->string('escalation_reason', 10)->nullable();
            $table->uuid('decided_by_employee_id')->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->date('requested_on');
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampsTz();

            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
            $table->foreign(['leave_type_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('leave_types')->restrictOnDelete();
            $table->foreign(['approver_employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
            $table->foreign(['decided_by_employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
            $table->unique(['id', 'tenant_id', 'employee_id'], 'leave_requests_id_tenant_employee_unique');
            $table->index(['tenant_id', 'status', 'starts_on']);
            $table->index(['tenant_id', 'approver_employee_id', 'status']);
            $table->index(['tenant_id', 'employee_id', 'starts_on']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE leave_requests
                ADD CONSTRAINT leave_requests_ends_after_it_starts CHECK (ends_on >= starts_on),
                ADD CONSTRAINT leave_requests_working_days_positive CHECK (working_days > 0),
                ADD CONSTRAINT leave_requests_status_known CHECK (status IN ('requested', 'approved', 'rejected', 'cancelled', 'taken')),
                ADD CONSTRAINT leave_requests_short_by_not_negative CHECK (short_by >= 0),
                ADD CONSTRAINT leave_requests_balance_only_for_vacations CHECK ((leave_type_id IS NULL) = (had_balance IS NOT NULL)),
                ADD CONSTRAINT leave_requests_escalation_whole CHECK ((escalated_at IS NULL) = (escalation_reason IS NULL)),
                ADD CONSTRAINT leave_requests_escalation_reason_known CHECK (escalation_reason IN ('no_manager', 'timeout')),
                ADD CONSTRAINT leave_requests_decided_when CHECK (status NOT IN ('approved', 'rejected', 'taken') OR decided_at IS NOT NULL),
                ADD CONSTRAINT leave_requests_rejection_explained CHECK (status <> 'rejected' OR length(btrim(coalesce(rejection_reason, ''))) > 0),
                ADD CONSTRAINT leave_requests_cancelled_when CHECK (status <> 'cancelled' OR cancelled_at IS NOT NULL);

            ALTER TABLE leave_entries
                ADD CONSTRAINT leave_entries_request_of_the_person FOREIGN KEY (leave_request_id, tenant_id, employee_id)
                    REFERENCES leave_requests (id, tenant_id, employee_id) ON DELETE RESTRICT,
                DROP CONSTRAINT leave_entries_adjustment_explained,
                ADD CONSTRAINT leave_entries_adjustment_explained CHECK (
                    kind <> 'adjustment' OR leave_request_id IS NOT NULL OR (recorded_by IS NOT NULL AND length(btrim(coalesce(note, ''))) > 0)
                );

            CREATE UNIQUE INDEX leave_entries_one_charge_per_request
                ON leave_entries (leave_request_id, coalesce(leave_entitlement_id, '00000000-0000-0000-0000-000000000000'::uuid), tranche)
                WHERE kind = 'taken' AND leave_request_id IS NOT NULL;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP INDEX IF EXISTS leave_entries_one_charge_per_request;

            ALTER TABLE leave_entries
                DROP CONSTRAINT leave_entries_request_of_the_person,
                DROP CONSTRAINT leave_entries_adjustment_explained,
                ADD CONSTRAINT leave_entries_adjustment_explained CHECK (
                    kind <> 'adjustment' OR (recorded_by IS NOT NULL AND length(btrim(coalesce(note, ''))) > 0)
                );
        SQL);
        Schema::dropIfExists('leave_requests');
    }
};
