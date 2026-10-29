<?php

declare(strict_types=1);

namespace Database\Factories\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantMembership>
 */
final class TenantMembershipFactory extends Factory
{
    /**
     * @return array<model-property<TenantMembership>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'role' => TenantRole::Owner,
        ];
    }

    public function hr(): self
    {
        return $this->state(['role' => TenantRole::Hr]);
    }

    public function viewer(): self
    {
        return $this->state(['role' => TenantRole::Viewer]);
    }
}
