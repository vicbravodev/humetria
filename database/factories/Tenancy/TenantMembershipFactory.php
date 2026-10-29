<?php

declare(strict_types=1);

namespace Database\Factories\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Support\CompanyRoles;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A person in a company, as its owner unless a role is given: the role is assigned in Spatie once the row exists.
 *
 * @extends Factory<TenantMembership>
 */
final class TenantMembershipFactory extends Factory
{
    private TenantRole $role = TenantRole::Owner;

    /**
     * @return array<model-property<TenantMembership>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
        ];
    }

    /** The role the membership gets instead of owner. */
    public function role(TenantRole $role): self
    {
        $factory = $this->newInstance();
        $factory->role = $role;

        return $factory;
    }

    public function hr(): self
    {
        return $this->role(TenantRole::Hr);
    }

    public function viewer(): self
    {
        return $this->role(TenantRole::Viewer);
    }

    /**
     * Every chained call builds a new factory: the role travels with it.
     *
     * @param  array<string, mixed>  $arguments
     */
    protected function newInstance(array $arguments = []): self
    {
        $factory = parent::newInstance($arguments);
        $factory->role = $this->role;

        return $factory;
    }

    /**
     * @param  Collection<int, Model>  $instances
     */
    protected function callAfterCreating(Collection $instances, ?Model $parent = null): void
    {
        $roles = resolve(CompanyRoles::class);

        foreach ($instances as $membership) {
            if ($membership instanceof TenantMembership) {
                $roles->assign($membership->user()->sole(), $membership->tenant()->sole(), $this->role);
            }
        }

        parent::callAfterCreating($instances, $parent);
    }
}
