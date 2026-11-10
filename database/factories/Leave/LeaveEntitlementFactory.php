<?php

declare(strict_types=1);

namespace Database\Factories\Leave;

use App\Modules\Leave\Enums\EntryKind;
use App\Modules\Leave\Enums\Tranche;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Support\PeriodWindow;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use InvalidArgumentException;

/**
 * The period of the first year of service of a person of its own company, with the days of the law: anniversary on 15
 * March 2026. Its window follows the anniversary. On its own it has no entries: withGrant() writes them.
 *
 * @extends Factory<LeaveEntitlement>
 */
final class LeaveEntitlementFactory extends Factory
{
    protected $model = LeaveEntitlement::class;

    /**
     * @return array<model-property<LeaveEntitlement>, mixed>
     */
    public function definition(): array
    {
        $window = fn (array $attributes): PeriodWindow => PeriodWindow::of(CarbonImmutable::parse(is_string($attributes['anniversary_on']) ? $attributes['anniversary_on'] : throw new InvalidArgumentException('The anniversary of a period is a date as text: Y-m-d.')));

        return [
            'tenant_id' => TenantFactory::new(),
            'employee_id' => fn (array $attributes) => EmployeeFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'years_of_service' => 1,
            'anniversary_on' => '2026-03-15',
            'policy_name' => 'Ley',
            'premium_percent' => 25,
            'statutory_days' => 12,
            'extra_days' => 0,
            'granted_on' => fn (array $attributes) => $attributes['anniversary_on'],
            'enjoy_by' => fn (array $attributes) => $window($attributes)->enjoyBy->toDateString(),
            'prescribes_on' => fn (array $attributes) => $window($attributes)->prescribesOn->toDateString(),
            'source' => LeaveEntitlement::ANNIVERSARY,
        ];
    }

    /** With the entries that grant its days, one per tranche that has any. */
    public function withGrant(): self
    {
        return $this->afterCreating(function (LeaveEntitlement $period): void {
            foreach ([[Tranche::Statutory, $period->statutory_days], [Tranche::Extra, $period->extra_days]] as [$tranche, $days]) {
                if ($days > 0) {
                    LeaveEntryFactory::new()->create([
                        'tenant_id' => $period->tenant_id, 'employee_id' => $period->employee_id, 'leave_entitlement_id' => $period->id,
                        'kind' => EntryKind::Granted, 'tranche' => $tranche, 'days' => $days,
                    ]);
                }
            }
        });
    }
}
