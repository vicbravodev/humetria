<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The benefits a company grants (aguinaldo, vales, a bonus of its own): what each one is, who gets it and how it weighs on
 * the salario base de cotización and on the other bases. The money (days, percent, pesos) is not here but in
 * contract_terms, which has its vigencia. The key (id, tenant_id) lets a term point to a benefit of its own company only.
 *
 * base_key marks the benefits every company starts with; a benefit the company defines has none. job_grade_ids is a list
 * of job grades (ids of the company); a json list cannot carry a foreign key, so the action checks them.
 */
return new class extends Migration
{
    private const array BASE_KEYS = [
        'christmas_bonus', 'vacation', 'vacation_premium', 'savings_fund', 'food_vouchers', 'seniority_premium',
        'sunday_premium', 'productivity_bonus', 'attendance_bonus', 'major_medical', 'retirement_fund',
    ];

    public function up(): void
    {
        Schema::create('benefits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->string('base_key', 40)->nullable();
            $table->string('kind', 20);
            $table->string('frequency', 20)->default('yearly');
            $table->string('applies_to_group', 20)->nullable();
            $table->jsonb('job_grade_ids')->default('[]');
            $table->unsignedSmallInteger('min_tenure_months')->default(0);
            $table->string('sbc_integration', 20);
            $table->bigInteger('sbc_cap_cents')->nullable();
            $table->boolean('in_payroll_tax')->default(false);
            $table->boolean('in_ptu_base')->default(false);
            $table->boolean('in_seniority_base')->default(false);
            $table->boolean('income_tax_exempt')->default(false);
            $table->bigInteger('cap_cents')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();
            $table->unique(['id', 'tenant_id'], 'benefits_id_tenant_unique');
            $table->index(['tenant_id', 'archived_at']);
        });

        $checks = [
            'benefits_name_filled' => "btrim(name) <> ''",
            'benefits_kind_known' => 'kind IN ('.$this->in(['salary_days', 'salary_percent', 'fixed_amount', 'uma_multiple']).')',
            'benefits_frequency_known' => 'frequency IN ('.$this->in(['daily', 'monthly', 'yearly']).')',
            'benefits_group_known' => 'applies_to_group IS NULL OR applies_to_group IN ('.$this->in(['unionized', 'trusted']).')',
            'benefits_sbc_integration_known' => 'sbc_integration IN ('.$this->in(['always', 'never', 'up_to_cap']).')',
            'benefits_base_key_known' => 'base_key IS NULL OR base_key IN ('.$this->in(self::BASE_KEYS).')',
            'benefits_job_grades_list' => "jsonb_typeof(job_grade_ids) = 'array'",
            'benefits_amounts_not_negative' => 'min_tenure_months >= 0 AND sbc_cap_cents >= 0 AND cap_cents >= 0',
            // A cap goes with "up to a cap" and nothing else. Only a base benefit may leave it empty: its cap is the one the
            // law sets (vales: 40 % of the UMA), which changes every year, so it is not a number kept here.
            'benefits_sbc_cap_matches' => "(sbc_integration = 'up_to_cap' OR sbc_cap_cents IS NULL) AND (sbc_integration <> 'up_to_cap' OR sbc_cap_cents IS NOT NULL OR base_key IS NOT NULL)",
            'benefits_base_never_archived' => 'base_key IS NULL OR archived_at IS NULL',
        ];

        foreach ($checks as $name => $check) {
            DB::statement("ALTER TABLE benefits ADD CONSTRAINT {$name} CHECK ({$check})");
        }

        DB::statement('CREATE UNIQUE INDEX benefits_tenant_name_unique ON benefits (tenant_id, lower(btrim(name)))');
        DB::statement('CREATE UNIQUE INDEX benefits_tenant_base_key_unique ON benefits (tenant_id, base_key) WHERE base_key IS NOT NULL');
    }

    /**
     * @param  list<string>  $values
     */
    private function in(array $values): string
    {
        return implode(', ', array_map(fn (string $value): string => "'{$value}'", $values));
    }

    public function down(): void
    {
        Schema::dropIfExists('benefits');
    }
};
