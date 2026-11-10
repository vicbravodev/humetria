<?php

declare(strict_types=1);

namespace Database\Factories\Leave;

use App\Modules\Leave\Enums\RowKind;
use App\Modules\Leave\Models\LeavePolicyRow;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A tier of 20 days for the first five years, in a policy of its own company.
 *
 * @extends Factory<LeavePolicyRow>
 */
final class LeavePolicyRowFactory extends Factory
{
    protected $model = LeavePolicyRow::class;

    /**
     * @return array<model-property<LeavePolicyRow>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'leave_policy_id' => fn (array $attributes) => LeavePolicyFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'kind' => RowKind::Tier,
            'year_from' => 1,
            'year_to' => 5,
            'days' => 20,
        ];
    }

    public function fixed(string $label = 'Cumpleaños', int $days = 1): self
    {
        return $this->state(['kind' => RowKind::Fixed, 'label' => $label, 'year_from' => null, 'year_to' => null, 'days' => $days]);
    }
}
