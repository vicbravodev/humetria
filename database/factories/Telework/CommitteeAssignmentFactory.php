<?php

declare(strict_types=1);

namespace Database\Factories\Telework;

use App\Models\User;
use App\Modules\Telework\Models\CommitteeAssignment;
use Database\Factories\Tenancy\TenantFactory;
use Database\Factories\Tenancy\WorkCenterFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommitteeAssignment>
 */
final class CommitteeAssignmentFactory extends Factory
{
    protected $model = CommitteeAssignment::class;

    /**
     * @return array<model-property<CommitteeAssignment>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'work_center_id' => fn (array $attributes) => WorkCenterFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'user_id' => User::factory(),
        ];
    }
}
