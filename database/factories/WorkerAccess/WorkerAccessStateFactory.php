<?php

declare(strict_types=1);

namespace Database\Factories\WorkerAccess;

use App\Modules\WorkerAccess\Models\WorkerAccessState;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkerAccessState>
 */
final class WorkerAccessStateFactory extends Factory
{
    protected $model = WorkerAccessState::class;

    /**
     * @return array<model-property<WorkerAccessState>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'employee_id' => fn (array $attributes) => EmployeeFactory::new()->createOne(['tenant_id' => $attributes['tenant_id']])->id,
            'valid_after' => now(),
        ];
    }
}
