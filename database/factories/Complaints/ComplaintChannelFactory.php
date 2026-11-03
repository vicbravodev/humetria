<?php

declare(strict_types=1);

namespace Database\Factories\Complaints;

use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Support\RandomCode;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A channel that was set up and not yet activated.
 *
 * @extends Factory<ComplaintChannel>
 */
final class ComplaintChannelFactory extends Factory
{
    protected $model = ComplaintChannel::class;

    /**
     * @return array<model-property<ComplaintChannel>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'access_code' => RandomCode::make(16),
            'enabled_at' => null,
            'paused_at' => null,
            'published_on' => null,
            'access_code_rotated_on' => null,
            'procedure' => 'Recibimos la queja, acusamos recibo y la atiende una persona designada.',
            'acknowledge_within_days' => 3,
            'resolve_within_days' => 30,
            'retention_months' => 24,
        ];
    }

    /** The company's channel; the module does not reach Tenancy's models, so `for()` has no relation to use. */
    public function forTenant(string $tenantId): self
    {
        return $this->state(['tenant_id' => $tenantId]);
    }

    public function active(): self
    {
        return $this->state(['enabled_at' => now(), 'published_on' => now()->toDateString()]);
    }

    public function paused(): self
    {
        return $this->active()->state(['paused_at' => now()]);
    }
}
