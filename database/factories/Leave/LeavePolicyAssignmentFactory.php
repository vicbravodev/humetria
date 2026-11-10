<?php

declare(strict_types=1);

namespace Database\Factories\Leave;

use App\Modules\Leave\Enums\PolicyScope;
use App\Modules\Leave\Models\LeavePolicyAssignment;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A policy of its own company as the default of that company.
 *
 * @extends Factory<LeavePolicyAssignment>
 */
final class LeavePolicyAssignmentFactory extends Factory
{
    protected $model = LeavePolicyAssignment::class;

    /**
     * @return array<model-property<LeavePolicyAssignment>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'leave_policy_id' => fn (array $attributes) => LeavePolicyFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'scope' => PolicyScope::Default,
        ];
    }
}
