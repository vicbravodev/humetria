<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vacaciones: what a person was owed, in days, the day the relationship ended (criterio C-07): what was left of their
 * periods and the part of the year in course. No amount of money: that is the company's payroll. One open at a time per
 * person: settled ones stay as history, and one not yet settled is voided if the person comes back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_terminations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('employee_id');
            $table->date('terminated_on');
            $table->smallInteger('pending_days');
            $table->decimal('proportional_days', 5, 2);
            $table->timestampTz('settled_at')->nullable();
            $table->foreignId('settled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('voided_at')->nullable();

            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
            $table->index(['tenant_id', 'employee_id']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE leave_terminations
                ADD CONSTRAINT leave_terminations_proportional_not_negative CHECK (proportional_days >= 0),
                ADD CONSTRAINT leave_terminations_settled_whole CHECK ((settled_at IS NULL) = (settled_by IS NULL)),
                ADD CONSTRAINT leave_terminations_settled_or_voided CHECK (settled_at IS NULL OR voided_at IS NULL);

            CREATE UNIQUE INDEX leave_terminations_one_open_per_person
                ON leave_terminations (tenant_id, employee_id)
                WHERE settled_at IS NULL AND voided_at IS NULL;
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_terminations');
    }
};
