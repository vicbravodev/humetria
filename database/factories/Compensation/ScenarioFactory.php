<?php

declare(strict_types=1);

namespace Database\Factories\Compensation;

use App\Modules\Compensation\Enums\AssumptionKey;
use App\Modules\Compensation\Models\Scenario;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A 5 % raise for the unionized from 2027-01-01, costed over 3 years with no retroactivity and the platform's assumptions.
 *
 * @extends Factory<Scenario>
 */
final class ScenarioFactory extends Factory
{
    /** Named here so the factory also works when used on its own, as other modules' factories do. */
    protected $model = Scenario::class;

    /**
     * @return array<model-property<Scenario>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'name' => 'Revisión '.fake()->unique()->numberBetween(2027, 2999),
            'effective_on' => '2027-01-01',
            'retroactive_months' => 0,
            'horizon_years' => 3,
            'group_changes' => ['unionized' => 500, 'trusted' => null],
            'term_changes' => [],
            'assumptions' => AssumptionKey::defaultSnapshot(),
            'default_state' => null,
            'archived_at' => null,
        ];
    }

    public function archived(): self
    {
        return $this->state(['archived_at' => now()]);
    }
}
