<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Models\User;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Exceptions\NotACompanyRole;
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

    /** @throws NotACompanyRole when the role is a template or belongs to no company. */
    public function assertCustom(Role $role): void
    {
        (str_starts_with($role->name, 'custom_') && $role->getAttribute('tenant_id') !== null) || throw new NotACompanyRole;
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

    /**
     * The permissions given to the person themselves in the company, by name.
     *
     * @return list<string>
     */
    public function directPermissionsOf(User $user, Tenant $tenant): array
    {
        $names = [];

        foreach (DB::table('model_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('model_has_permissions.tenant_id', $tenant->id)
            ->where('model_has_permissions.model_type', $user->getMorphClass())
            ->where('model_has_permissions.model_id', $user->id)
            ->orderBy('permissions.name')
            ->get(['permissions.name']) as $row) {
            is_string($row->name) && $names[] = $row->name;
        }

        return $names;
    }

    /**
     * Every permission the person has in the company and where it comes from (the role wins when both give it),
     * leaving out those of a module the company does not have.
     *
     * @return array<string, 'role'|'direct'>
     */
    public function effectivePermissions(User $user, Tenant $tenant): array
    {
        $effective = [];

        foreach ($this->roleOf($user, $tenant)?->permissions->all() ?? [] as $permission) {
            $effective[$permission->name] = 'role';
        }

        foreach ($this->directPermissionsOf($user, $tenant) as $name) {
            $effective[$name] ??= 'direct';
        }

        return array_filter($effective, fn (string $source, string $name): bool => ($definition = PermissionCatalog::find($name)) === null
            || PermissionCatalog::unavailableReason($tenant, $definition) === null, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Replaces the person's direct permissions in the company with `$permissions`.
     *
     * @param  list<string>  $permissions
     */
    public function syncDirectPermissions(User $user, Tenant $tenant, array $permissions): void
    {
        $this->inTeam($user, $tenant, fn () => $user->syncPermissions($permissions));
    }

    /** Takes the person's role and direct permissions in the company away; what they have elsewhere, and the superadmin role, stay. */
    public function remove(User $user, Tenant $tenant): void
    {
        $this->inTeam($user, $tenant, function () use ($user): void {
            $user->syncRoles([]);
            $user->syncPermissions([]);
        });
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
