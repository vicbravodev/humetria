<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The individual salary: what a person really earns when it is not the salary of their category and level, in whole cents,
 * from a date on. A person has one per date, so the history is kept and the one in force on a day is the latest that
 * started on or before it.
 *
 * The person is told apart by the company's own employee number (the ID de la empresa), which is how Organization hands
 * people to Compensation: there is no key to employees, because Compensation never learns their internal id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_overrides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->string('employee_number', 50);
            $table->bigInteger('daily_salary_cents');
            $table->date('effective_from');
            $table->string('note', 500)->nullable();
            $table->timestampsTz();
            $table->index(['tenant_id', 'employee_number']);
        });

        DB::statement('ALTER TABLE salary_overrides ADD CONSTRAINT salary_overrides_salary_positive CHECK (daily_salary_cents > 0)');
        DB::statement("ALTER TABLE salary_overrides ADD CONSTRAINT salary_overrides_number_filled CHECK (btrim(employee_number) <> '')");
        DB::statement('CREATE UNIQUE INDEX salary_overrides_person_date_unique ON salary_overrides (tenant_id, lower(employee_number), effective_from)');
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_overrides');
    }
};
