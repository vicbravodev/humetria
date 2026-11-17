<?php

declare(strict_types=1);

namespace Database\Factories\Sso;

use App\Modules\Sso\Models\SsoDomain;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A domain a company claims; by default, not proven yet.
 *
 * @extends Factory<SsoDomain>
 */
final class SsoDomainFactory extends Factory
{
    protected $model = SsoDomain::class;

    /**
     * @return array<model-property<SsoDomain>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'domain' => 'acme.mx',
            'token' => Str::random(40),
            'verified_at' => null,
        ];
    }

    public function verified(): self
    {
        return $this->state(['verified_at' => now()]);
    }
}
