<?php

declare(strict_types=1);

namespace Database\Factories\Compensation;

use App\Modules\Compensation\Models\Benefit;
use App\Modules\Compensation\Models\ContractTerm;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A term from 2026-01-01 of 5 % (500 hundredths) of the benefit it is for, which belongs to the same company.
 *
 * @extends Factory<ContractTerm>
 */
final class ContractTermFactory extends Factory
{
    /** Named here so the factory also works when used on its own, as other modules' factories do. */
    protected $model = ContractTerm::class;

    /**
     * @return array<model-property<ContractTerm>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'benefit_id' => fn (array $attributes) => BenefitFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'effective_from' => '2026-01-01',
            'value' => 500,
            'tenure_table' => null,
        ];
    }

    public function forBenefit(Benefit $benefit): self
    {
        return $this->state(['tenant_id' => $benefit->tenant_id, 'benefit_id' => $benefit->id]);
    }
}
