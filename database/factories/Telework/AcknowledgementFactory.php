<?php

declare(strict_types=1);

namespace Database\Factories\Telework;

use App\Modules\Telework\Enums\AckChannel;
use App\Modules\Telework\Models\Acknowledgement;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A pending request for an employee to acknowledge a document.
 *
 * @extends Factory<Acknowledgement>
 */
final class AcknowledgementFactory extends Factory
{
    protected $model = Acknowledgement::class;

    /**
     * @return array<model-property<Acknowledgement>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'document_id' => fn (array $attributes) => TeleworkDocumentFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'employee_id' => fn (array $attributes) => EmployeeFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'requested_at' => now(),
        ];
    }

    public function acknowledged(AckChannel $channel = AckChannel::Portal): self
    {
        return $this->state(['channel' => $channel, 'acknowledged_at' => now()]);
    }
}
