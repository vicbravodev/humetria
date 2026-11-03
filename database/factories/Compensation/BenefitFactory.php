<?php

declare(strict_types=1);

namespace Database\Factories\Compensation;

use App\Modules\Compensation\Enums\BaseBenefit;
use App\Modules\Compensation\Enums\BenefitKind;
use App\Modules\Compensation\Enums\Frequency;
use App\Modules\Compensation\Enums\SbcIntegration;
use App\Modules\Compensation\Models\Benefit;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A benefit the company defined itself: a percent of the salary, for everyone, that integrates the SBC.
 *
 * @extends Factory<Benefit>
 */
final class BenefitFactory extends Factory
{
    /** Named here so the factory also works when used on its own, as other modules' factories do. */
    protected $model = Benefit::class;

    /**
     * @return array<model-property<Benefit>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'name' => 'Prestación '.fake()->unique()->numberBetween(1, 999999),
            'base_key' => null,
            'kind' => BenefitKind::SalaryPercent,
            'frequency' => Frequency::Yearly,
            'applies_to_group' => null,
            'job_grade_ids' => [],
            'min_tenure_months' => 0,
            'sbc_integration' => SbcIntegration::Always,
            'sbc_cap_cents' => null,
            'in_payroll_tax' => true,
            'in_ptu_base' => false,
            'in_seniority_base' => false,
            'income_tax_exempt' => false,
            'cap_cents' => null,
            'archived_at' => null,
        ];
    }

    /** One of the base benefits, by its key. */
    public function base(string $key): self
    {
        return $this->state(['base_key' => $key, 'name' => BaseBenefit::from($key)->label()]);
    }

    public function archived(): self
    {
        return $this->state(['archived_at' => now()]);
    }
}
