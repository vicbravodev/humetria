<?php

declare(strict_types=1);

namespace Database\Factories\Sso;

use App\Modules\Sso\Models\SsoConnection;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A company's identity provider, set up but not switched on.
 *
 * @extends Factory<SsoConnection>
 */
final class SsoConnectionFactory extends Factory
{
    protected $model = SsoConnection::class;

    /**
     * @return array<model-property<SsoConnection>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'issuer' => 'https://idp.acme.test',
            'client_id' => 'humetria-acme',
            'client_secret' => 'super-secret-value',
            'enabled' => false,
            'enforced' => false,
        ];
    }

    public function enabled(): self
    {
        return $this->state(['enabled' => true]);
    }

    public function enforced(): self
    {
        return $this->state(['enabled' => true, 'enforced' => true]);
    }
}
