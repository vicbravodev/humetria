<?php

declare(strict_types=1);

namespace Database\Factories\Compensation;

use App\Modules\Compensation\Models\WorkRiskPremium;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The prima media of class II (1.13065 %) from 2026-01-01.
 *
 * @extends Factory<WorkRiskPremium>
 */
final class WorkRiskPremiumFactory extends Factory
{
    /** Named here so the factory also works when used on its own, as other modules' factories do. */
    protected $model = WorkRiskPremium::class;

    /**
     * @return array<model-property<WorkRiskPremium>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'effective_from' => '2026-01-01',
            'rate_scaled' => 113_065,
        ];
    }
}
