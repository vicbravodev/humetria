<?php

declare(strict_types=1);

namespace Database\Factories\Leave;

use App\Modules\Leave\Enums\EntryKind;
use App\Modules\Leave\Enums\Tranche;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Twelve statutory days granted on a period of its own. To write on an existing period: of($period).
 *
 * @extends Factory<LeaveEntry>
 */
final class LeaveEntryFactory extends Factory
{
    protected $model = LeaveEntry::class;

    /**
     * @return array<model-property<LeaveEntry>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'leave_entitlement_id' => fn (array $attributes) => LeaveEntitlementFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'employee_id' => fn (array $attributes) => LeaveEntitlement::query()->withoutGlobalScopes()->whereKey($attributes['leave_entitlement_id'])->sole()->employee_id,
            'kind' => EntryKind::Granted,
            'tranche' => Tranche::Statutory,
            'days' => 12,
        ];
    }

    public function of(LeaveEntitlement $period): self
    {
        return $this->state(['tenant_id' => $period->tenant_id, 'employee_id' => $period->employee_id, 'leave_entitlement_id' => $period->id]);
    }

    public function taken(int $days, Tranche $tranche = Tranche::Statutory): self
    {
        return $this->state(['kind' => EntryKind::Taken, 'tranche' => $tranche, 'days' => -$days]);
    }
}
