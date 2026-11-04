<?php

declare(strict_types=1);

namespace Database\Factories\Compensation;

use App\Modules\Compensation\Models\GradeSalary;
use Database\Factories\Organization\JobGradeFactory;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A salary of $500.00 a day from 2026-01-01 for a job grade of the same company.
 *
 * @extends Factory<GradeSalary>
 */
final class GradeSalaryFactory extends Factory
{
    /** Named here so the factory also works when used on its own, as other modules' factories do. */
    protected $model = GradeSalary::class;

    /**
     * @return array<model-property<GradeSalary>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'job_grade_id' => fn (array $attributes) => JobGradeFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'daily_salary_cents' => 50000,
            'effective_from' => '2026-01-01',
        ];
    }
}
