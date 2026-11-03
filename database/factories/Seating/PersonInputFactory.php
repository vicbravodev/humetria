<?php

declare(strict_types=1);

namespace Database\Factories\Seating;

use App\Modules\Seating\Models\PersonInput;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Tenancy\TenantFactory;
use Database\Factories\Tenancy\WorkCenterFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An answer of acceptable footwear and occasional discomfort, sent a week ago.
 *
 * @extends Factory<PersonInput>
 */
final class PersonInputFactory extends Factory
{
    protected $model = PersonInput::class;

    /**
     * @return array<model-property<PersonInput>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'employee_id' => fn (array $attributes) => EmployeeFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'work_center_id' => fn (array $attributes) => WorkCenterFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'footwear' => 2,
            'discomfort' => 2,
            'note' => null,
            'notice_accepted_at' => now()->subWeek(),
            'submitted_at' => now()->subWeek(),
        ];
    }
}
