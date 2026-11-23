<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Models\User;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * A person's role inside a company, kept by spatie/laravel-permission with the company as its team.
 * Spatie reads and writes roles in "the current team"; each method here switches to the company it is told about
 * and puts the previous team back, so working on one company never disturbs the request's own.
 */
final readonly class CompanyRoles
{
    public function __construct(private PermissionRegistrar $registrar) {}

    public function of(User $user, Tenant $tenant): ?TenantRole
    {
        return $this->inCompany($tenant)[$user->id] ?? null;
    }

    /**
     * The company's own roles, by label.
     *
     * @return Collection<int, Role>
     */
    public function customRoles(Tenant $tenant): Collection
    {
        return Role::query()->where('tenant_id', $tenant->id)->where('name', 'like', 'custom\_%')->orderBy('label')->get();
    }

    /** The person's role in the company, a template or one of its own. */
    public function roleOf(User $user, Tenant $tenant): ?Role
    {
        $id = DB::table('model_has_roles')
            ->where('tenant_id', $tenant->id)
            ->where('model_type', $user->getMorphClass())
            ->where('model_id', $user->id)
            ->value('role_id');

        return is_int($id) ? Role::query()->find($id) : null;
    }

    /**
     * Ids of the people who hold the role.
     *
     * @return list<int>
     */
    public function holders(Role $role): array
    {
        $ids = [];

        foreach (DB::table('model_has_roles')->where('role_id', $role->id)->orderBy('model_id')->get(['model_id']) as $row) {
            is_int($row->model_id) && $ids[] = $row->model_id;
        }

        return $ids;
    }

    /** Whether a person holds the role or an open invitation names it. */
    public function inUse(Role $role): bool
    {
        return $this->holders($role) !== []
            || DB::table('tenant_invitations')->where('role', $role->name)->whereNull('accepted_at')->whereNull('cancelled_at')->exists();
    }

    /**
     * Checks every permission is one the company can hand out (not the owner's own, not a module it did not contract).
     *
     * @param  list<string>  $permissions
     * @return bool whether any of them is sensitive.
     *
     * @throws InvalidArgumentException
     */
    public function assertAssignable(Tenant $tenant, array $permissions): bool
    {
        $assignable = [];

        foreach (PermissionCatalog::assignable($tenant) as $definition) {
            if (PermissionCatalog::unavailableReason($tenant, $definition) === null) {
                $assignable[$definition->name] = $definition->sensitive;
            }
        }

        foreach ($permissions as $name) {
            isset($assignable[$name]) || throw new InvalidArgumentException("The company cannot hand out [$name].");
        }

        return array_any($permissions, fn (string $name): bool => $assignable[$name]);
    }

    /** Replaces the person's role in the company with `$role`. */
    public function assign(User $user, Tenant $tenant, TenantRole $role): void
    {
        $this->inTeam($user, $tenant, fn () => $user->syncRoles([$role->value]));
        $this->forgetMembers();
    }

    /** Takes the person's role in the company away; their roles elsewhere, and the superadmin one, stay. */
    public function remove(User $user, Tenant $tenant): void
    {
        $this->inTeam($user, $tenant, fn () => $user->syncRoles([]));
        $this->forgetMembers();
    }

    /**
     * Everyone's role in the company, by user id, in one query.
     *
     * @return array<int, TenantRole>
     */
    public function inCompany(Tenant $tenant): array
    {
        $roles = [];

        foreach (DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.tenant_id', $tenant->id)
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->orderBy('model_has_roles.model_id')
            ->get(['model_has_roles.model_id', 'roles.name']) as $row) {
            $role = is_string($row->name) ? TenantRole::tryFrom($row->name) : null;

            if ($role !== null && is_int($row->model_id)) {
                $roles[$row->model_id] = $role;
            }
        }

        return $roles;
    }

    /** The request's memo of the company's people is stale once a role changes. */
    private function forgetMembers(): void
    {
        resolve(CompanyMembers::class)->forget();
    }

    /**
     * @param  Closure(): mixed  $change
     */
    private function inTeam(User $user, Tenant $tenant, Closure $change): void
    {
        $previous = $this->registrar->getPermissionsTeamId();
        $this->registrar->setPermissionsTeamId($tenant->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        try {
            $change();
        } finally {
            $this->registrar->setPermissionsTeamId($previous);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
