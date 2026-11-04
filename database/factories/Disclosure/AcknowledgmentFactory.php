<?php

declare(strict_types=1);

namespace Database\Factories\Disclosure;

use App\Modules\Disclosure\Models\Acknowledgment;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A request to confirm a published policy, not yet confirmed. `portal()` confirms it from the person's link.
 *
 * @extends Factory<Acknowledgment>
 */
final class AcknowledgmentFactory extends Factory
{
    protected $model = Acknowledgment::class;

    /**
     * @return array<model-property<Acknowledgment>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'document_id' => fn (array $attributes) => DocumentFactory::new()->published()->state(['tenant_id' => $attributes['tenant_id']]),
            'employee_id' => fn (array $attributes) => EmployeeFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'channel' => null,
            'requested_at' => now(),
            'acknowledged_at' => null,
            'note' => null,
            'recorded_by' => null,
        ];
    }

    public function portal(): self
    {
        return $this->state(['channel' => 'portal', 'acknowledged_at' => now()]);
    }
}
