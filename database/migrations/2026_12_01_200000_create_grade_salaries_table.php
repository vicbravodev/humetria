<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The tabulator: the daily salary of a job grade (category and level) from a date on, in whole cents. A grade has one
 * salary per date, so its history is kept. The composite key makes a salary point to a grade of its own company only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_salaries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('job_grade_id');
            $table->bigInteger('daily_salary_cents');
            $table->date('effective_from');
            $table->timestampsTz();
            $table->foreign(['job_grade_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('job_grades')->restrictOnDelete();
            $table->unique(['job_grade_id', 'effective_from'], 'grade_salaries_grade_date_unique');
            $table->index(['tenant_id', 'job_grade_id']);
        });

        DB::statement('ALTER TABLE grade_salaries ADD CONSTRAINT grade_salaries_salary_positive CHECK (daily_salary_cents > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_salaries');
    }
};
