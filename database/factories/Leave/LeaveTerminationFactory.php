<?php

declare(strict_types=1);

namespace Database\Factories\Leave;

use App\Modules\Leave\Models\LeaveTermination;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The days a person of its own company was owed when they left on 2 July 2025, not yet settled.
 *
 * @extends Factory<LeaveTermination>
 */
final class LeaveTerminationFactory extends Factory
{
    protected $model = LeaveTermination::class;

    /**
     * @return array<model-property<LeaveTermination>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'employee_id' => fn (array $attributes) => EmployeeFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'terminated_on' => '2025-07-02',
            'pending_days' => 7,
            'proportional_days' => 5.98,
        ];
    }
}
