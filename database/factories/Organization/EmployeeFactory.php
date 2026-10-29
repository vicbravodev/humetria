<?php

declare(strict_types=1);

namespace Database\Factories\Organization;

use App\Modules\Organization\Enums\Gender;
use App\Modules\Organization\Models\Employee;
use Database\Factories\Tenancy\TenantFactory;
use Database\Factories\Tenancy\WorkCenterFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * An employee in a work center and an area of their own company.
 *
 * @extends Factory<Employee>
 */
final class EmployeeFactory extends Factory
{
    /**
     * @return array<model-property<Employee>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'work_center_id' => fn (array $attributes) => WorkCenterFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'area_id' => fn (array $attributes) => AreaFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'name' => fake()->name(),
            'email' => Str::lower(fake()->unique()->safeEmail()),
            'mobile' => fake()->numerify('55########'),
            'gender' => fake()->randomElement(Gender::cases()),
            'job_title' => fake()->randomElement(['Operador', 'Supervisor', 'Analista', 'Almacenista']),
            'employee_number' => null,
        ];
    }

    public function deactivated(): self
    {
        return $this->state(['deactivated_at' => now()]);
    }
}
