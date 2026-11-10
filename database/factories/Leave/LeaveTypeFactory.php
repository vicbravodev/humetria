<?php

declare(strict_types=1);

namespace Database\Factories\Leave;

use App\Modules\Leave\Models\LeaveType;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A paid leave type of the company's own, in use.
 *
 * @extends Factory<LeaveType>
 */
final class LeaveTypeFactory extends Factory
{
    protected $model = LeaveType::class;

    /**
     * @return array<model-property<LeaveType>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'name' => 'Permiso '.fake()->unique()->numberBetween(1, 99999),
            'paid' => true,
        ];
    }

    public function archived(): self
    {
        return $this->state(['archived_at' => now()]);
    }
}
