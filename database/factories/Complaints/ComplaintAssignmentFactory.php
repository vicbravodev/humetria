<?php

declare(strict_types=1);

namespace Database\Factories\Complaints;

use App\Models\User;
use App\Modules\Complaints\Models\ComplaintAssignment;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A current assignment made by the system. Give it the complaint of the same company.
 *
 * @extends Factory<ComplaintAssignment>
 */
final class ComplaintAssignmentFactory extends Factory
{
    protected $model = ComplaintAssignment::class;

    /**
     * @return array<model-property<ComplaintAssignment>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'complaint_id' => fn (array $attributes) => ComplaintFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'user_id' => User::factory(),
            'assigned_by' => null,
            'assigned_on' => now()->toDateString(),
            'ended_on' => null,
            'end_reason' => null,
            'notified_position' => 0,
        ];
    }

    public function ended(string $reason = 'recused'): self
    {
        return $this->state(['ended_on' => now()->toDateString(), 'end_reason' => $reason]);
    }
}
