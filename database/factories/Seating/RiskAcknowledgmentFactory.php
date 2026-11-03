<?php

declare(strict_types=1);

namespace Database\Factories\Seating;

use App\Modules\Seating\Models\RiskAcknowledgment;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A confirmation of a signed assessment, made yesterday.
 *
 * @extends Factory<RiskAcknowledgment>
 */
final class RiskAcknowledgmentFactory extends Factory
{
    protected $model = RiskAcknowledgment::class;

    /**
     * @return array<model-property<RiskAcknowledgment>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'employee_id' => fn (array $attributes) => EmployeeFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'assessment_id' => fn (array $attributes) => RiskAssessmentFactory::new()->signed()->state(['tenant_id' => $attributes['tenant_id']]),
            'acknowledged_at' => now()->subDay(),
        ];
    }
}
