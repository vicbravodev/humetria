<?php

declare(strict_types=1);

namespace Database\Factories\Leave;

use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveStatement;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The statement of a period of its own, issued on 15 March 2026 and not yet acknowledged.
 *
 * @extends Factory<LeaveStatement>
 */
final class LeaveStatementFactory extends Factory
{
    protected $model = LeaveStatement::class;

    /**
     * @return array<model-property<LeaveStatement>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'leave_entitlement_id' => fn (array $attributes) => LeaveEntitlementFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'employee_id' => fn (array $attributes) => LeaveEntitlement::query()->withoutGlobalScopes()->whereKey($attributes['leave_entitlement_id'])->sole()->employee_id,
            'issued_at' => '2026-03-15 18:00:00',
        ];
    }
}
