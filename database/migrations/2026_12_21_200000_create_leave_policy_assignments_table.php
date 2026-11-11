<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vacaciones: which policy goes to whom (criterio C-15): the default of the company, a staff group or a job grade. One
 * assignment per target; with none, the law applies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_policy_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('leave_policy_id');
            $table->string('scope', 11);
            $table->string('staff_group', 9)->nullable();
            $table->uuid('job_grade_id')->nullable();
            $table->timestampsTz();

            $table->foreign(['leave_policy_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('leave_policies')->restrictOnDelete();
            $table->foreign(['job_grade_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('job_grades')->restrictOnDelete();
            $table->index(['tenant_id', 'leave_policy_id']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE leave_policy_assignments
                ADD CONSTRAINT leave_policy_assignments_scope_shape CHECK (
                    (scope = 'default' AND staff_group IS NULL AND job_grade_id IS NULL)
                    OR (scope = 'staff_group' AND staff_group IS NOT NULL AND staff_group IN ('unionized', 'trusted') AND job_grade_id IS NULL)
                    OR (scope = 'job_grade' AND job_grade_id IS NOT NULL AND staff_group IS NULL)
                );

            CREATE UNIQUE INDEX leave_policy_assignments_one_default ON leave_policy_assignments (tenant_id) WHERE scope = 'default';
            CREATE UNIQUE INDEX leave_policy_assignments_one_per_staff_group ON leave_policy_assignments (tenant_id, staff_group) WHERE scope = 'staff_group';
            CREATE UNIQUE INDEX leave_policy_assignments_one_per_job_grade ON leave_policy_assignments (tenant_id, job_grade_id) WHERE scope = 'job_grade';
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_policy_assignments');
    }
};
