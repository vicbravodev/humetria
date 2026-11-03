<?php

declare(strict_types=1);

namespace Database\Factories\Seating;

use App\Modules\Seating\Models\PositionAssignment;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A person who started in a position a month ago and still holds it.
 *
 * @extends Factory<PositionAssignment>
 */
final class PositionAssignmentFactory extends Factory
{
    protected $model = PositionAssignment::class;

    /**
     * @return array<model-property<PositionAssignment>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'employee_id' => fn (array $attributes) => EmployeeFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'position_id' => fn (array $attributes) => StandingPositionFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'starts_on' => now()->subMonth()->toDateString(),
            'ends_on' => null,
        ];
    }
}
