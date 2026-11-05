<?php

declare(strict_types=1);

namespace Database\Factories\Compensation;

use App\Modules\Compensation\Models\SalaryOverride;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A salary of $600.00 a day from 2026-01-01 for the person with an employee number of the same company.
 *
 * @extends Factory<SalaryOverride>
 */
final class SalaryOverrideFactory extends Factory
{
    /** Named here so the factory also works when used on its own, as other modules' factories do. */
    protected $model = SalaryOverride::class;

    /**
     * @return array<model-property<SalaryOverride>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'employee_number' => 'E-'.fake()->unique()->numberBetween(1, 999999),
            'daily_salary_cents' => 60000,
            'effective_from' => '2026-01-01',
            'note' => null,
        ];
    }
}
