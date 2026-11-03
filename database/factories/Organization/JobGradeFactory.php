<?php

declare(strict_types=1);

namespace Database\Factories\Organization;

use App\Modules\Organization\Models\JobGrade;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobGrade>
 */
final class JobGradeFactory extends Factory
{
    /** Named here so the factory also works when used on its own, as the employees' factory does. */
    protected $model = JobGrade::class;

    /**
     * @return array<model-property<JobGrade>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'category' => 'Categoría '.fake()->unique()->numberBetween(1, 99999),
            'level' => fake()->randomElement(['A', 'B', 'C']),
        ];
    }
}
