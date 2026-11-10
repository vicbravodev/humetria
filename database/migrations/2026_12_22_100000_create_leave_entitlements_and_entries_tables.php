<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vacaciones: the period each year of service gives (criterios C-03, C-09, C-11 and C-15) and the ledger of its days. A
 * balance is not a counter: it is the sum of the entries of a period, and the ledger only takes inserts (criterio C-12), as
 * audit_entries and attendance_marks do. A period keeps the name and the terms of the policy it was granted with. An entry
 * with no period is days given in advance (criterio C-17). leave_request_id gets its foreign key in the migration of the requests table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_entitlements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('employee_id');
            $table->smallInteger('years_of_service');
            $table->date('anniversary_on');
            $table->uuid('leave_policy_id')->nullable();
            $table->string('policy_name', 120);
            $table->smallInteger('premium_percent');
            $table->smallInteger('statutory_days');
            $table->smallInteger('extra_days');
            $table->date('granted_on');
            $table->date('enjoy_by');
            $table->date('prescribes_on');
            $table->date('extra_expires_on')->nullable();
            $table->string('source', 11);
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
            $table->foreign(['leave_policy_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('leave_policies')->restrictOnDelete();
            $table->unique(['tenant_id', 'employee_id', 'years_of_service'], 'leave_entitlements_one_per_year_of_service');
            $table->unique(['id', 'tenant_id', 'employee_id'], 'leave_entitlements_id_tenant_employee_unique');
        });

        Schema::create('leave_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('employee_id');
            $table->uuid('leave_entitlement_id')->nullable();
            $table->string('kind', 11);
            $table->string('tranche', 9);
            $table->integer('days');
            $table->uuid('leave_request_id')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('authorized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
            $table->foreign(['leave_entitlement_id', 'tenant_id', 'employee_id'], 'leave_entries_period_of_the_person')
                ->references(['id', 'tenant_id', 'employee_id'])->on('leave_entitlements')->restrictOnDelete();
            $table->index(['tenant_id', 'employee_id']);
            $table->index(['tenant_id', 'leave_entitlement_id']);
            $table->index(['tenant_id', 'leave_request_id']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE leave_entitlements
                ADD CONSTRAINT leave_entitlements_years_from_the_first CHECK (years_of_service >= 1),
                ADD CONSTRAINT leave_entitlements_premium_floor CHECK (premium_percent >= 25),
                ADD CONSTRAINT leave_entitlements_days_not_negative CHECK (statutory_days >= 0 AND extra_days >= 0),
                ADD CONSTRAINT leave_entitlements_source_known CHECK (source IN ('anniversary', 'opening'));

            ALTER TABLE leave_entries
                ADD CONSTRAINT leave_entries_days_not_zero CHECK (days <> 0),
                ADD CONSTRAINT leave_entries_kind_known CHECK (kind IN ('granted', 'opening', 'taken', 'adjustment', 'expired', 'termination')),
                ADD CONSTRAINT leave_entries_tranche_known CHECK (tranche IN ('statutory', 'extra')),
                ADD CONSTRAINT leave_entries_adjustment_explained CHECK (
                    kind <> 'adjustment' OR (recorded_by IS NOT NULL AND length(btrim(coalesce(note, ''))) > 0)
                );

            CREATE OR REPLACE FUNCTION leave_entries_append_only() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'leave_entries is append-only';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER leave_entries_no_update_delete
                BEFORE UPDATE OR DELETE ON leave_entries
                FOR EACH ROW EXECUTE FUNCTION leave_entries_append_only();

            CREATE TRIGGER leave_entries_no_truncate
                BEFORE TRUNCATE ON leave_entries
                FOR EACH STATEMENT EXECUTE FUNCTION leave_entries_append_only();
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_entries');
        Schema::dropIfExists('leave_entitlements');
        DB::unprepared('DROP FUNCTION IF EXISTS leave_entries_append_only()');
    }
};
