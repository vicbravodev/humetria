<?php

declare(strict_types=1);

namespace Database\Factories\Telework;

use App\Modules\Telework\Models\PortalLink;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The link of an employee. Its token is `$token`, hashed as the portal looks it up.
 *
 * @extends Factory<PortalLink>
 */
final class PortalLinkFactory extends Factory
{
    protected $model = PortalLink::class;

    /**
     * @return array<model-property<PortalLink>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'employee_id' => fn (array $attributes) => EmployeeFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'token_hash' => PortalLink::hashToken(fake()->unique()->sha256()),
        ];
    }

    /** The link whose address is /portal/{token}. */
    public function token(string $token): self
    {
        return $this->state(['token_hash' => PortalLink::hashToken($token)]);
    }
}
