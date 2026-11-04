<?php

declare(strict_types=1);

namespace Database\Factories\Complaints;

use App\Models\User;
use App\Modules\Complaints\Models\ComplaintHandler;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A designation for the whole company that is open.
 *
 * @extends Factory<ComplaintHandler>
 */
final class ComplaintHandlerFactory extends Factory
{
    protected $model = ComplaintHandler::class;

    /**
     * @return array<model-property<ComplaintHandler>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'user_id' => User::factory(),
            'work_center_id' => null,
            'designated_by' => fn (array $attributes) => $attributes['user_id'],
            'designated_at' => now(),
            'ended_at' => null,
        ];
    }

    /** Open, for the whole company. */
    public function company(string $tenantId): self
    {
        return $this->state(['tenant_id' => $tenantId, 'work_center_id' => null]);
    }

    /** Open, for one work center of the company. */
    public function forCenter(string $tenantId, string $workCenterId): self
    {
        return $this->state(['tenant_id' => $tenantId, 'work_center_id' => $workCenterId]);
    }

    public function ended(): self
    {
        return $this->state(['designated_at' => now()->subDay(), 'ended_at' => now()]);
    }
}
