<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A scenario can be costed on the roster (as every scenario was until now) or on an average payroll: a headcount, an average
 * monthly salary and the years of service of the average worker, for a company that has not loaded its people or only wants a
 * first number. `pay_basis` says how the salary was agreed: a daily rate pays 365 days a year, a monthly pay 12 months of
 * thirty days. An average payroll is always paid monthly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scenarios', function (Blueprint $table) {
            $table->string('source', 10)->default('roster');
            $table->unsignedInteger('average_workers')->nullable();
            $table->unsignedBigInteger('average_monthly_salary_cents')->nullable();
            $table->unsignedSmallInteger('average_tenure_years')->nullable();
            $table->string('average_group', 10)->nullable();
            $table->string('pay_basis', 10)->default('daily');
        });

        DB::statement("ALTER TABLE scenarios ADD CONSTRAINT scenarios_source_known CHECK (source IN ('roster', 'average'))");
        DB::statement("ALTER TABLE scenarios ADD CONSTRAINT scenarios_pay_basis_known CHECK (pay_basis IN ('daily', 'monthly'))");
        DB::statement("ALTER TABLE scenarios ADD CONSTRAINT scenarios_average_group_known CHECK (average_group IS NULL OR average_group IN ('unionized', 'trusted'))");
        DB::statement("ALTER TABLE scenarios ADD CONSTRAINT scenarios_average_complete CHECK (source = 'roster' OR (average_workers > 0 AND average_monthly_salary_cents > 0 AND average_tenure_years IS NOT NULL AND average_group IS NOT NULL AND pay_basis = 'monthly'))");
    }

    public function down(): void
    {
        Schema::table('scenarios', function (Blueprint $table) {
            $table->dropColumn(['source', 'average_workers', 'average_monthly_salary_cents', 'average_tenure_years', 'average_group', 'pay_basis']);
        });
    }
};
