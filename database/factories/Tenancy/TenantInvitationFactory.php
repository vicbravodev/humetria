<?php

declare(strict_types=1);

namespace Database\Factories\Tenancy;

use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TenantInvitation>
 */
final class TenantInvitationFactory extends Factory
{
    /**
     * @return array<model-property<TenantInvitation>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'role' => TenantRole::Owner,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'token_hash' => TenantInvitation::hashToken(Str::random(64)),
            'expires_at' => now()->addDays(TenantInvitation::VALID_DAYS),
        ];
    }

    public function withToken(string $token): self
    {
        return $this->state(['token_hash' => TenantInvitation::hashToken($token)]);
    }

    public function expired(): self
    {
        return $this->state(['expires_at' => now()->subSecond()]);
    }

    public function accepted(): self
    {
        return $this->state(['accepted_at' => now()]);
    }

    public function cancelled(): self
    {
        return $this->state(['cancelled_at' => now()]);
    }
}
