<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Models\User;
use App\Modules\Tenancy\Data\CompanyRoleSummary;
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

    /** The person's role in the company, a template or one of its own; null when they have none. */
    public function of(User $user, Tenant $tenant): ?CompanyRoleSummary
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
            || DB::table('tenant_invitations')->where('role_id', $role->id)->whereNull('accepted_at')->whereNull('cancelled_at')->exists();
    }

    /** The system's role of that template. */
    public function template(TenantRole $role): Role
    {
        return Role::query()->where('name', $role->value)->whereNull('tenant_id')->sole();
    }

    /**
     * The role the owner can give someone, by its key: a template the account hands out, or one of the company's own.
     * Never the owner's, nor a role of another company.
     */
    public function assignable(Tenant $tenant, string $key): ?Role
    {
        if (($template = TenantRole::tryFrom($key)) !== null) {
            return in_array($template, $tenant->type->assignableRoles(), true) ? $this->template($template) : null;
        }

        return Role::query()->where('tenant_id', $tenant->id)->where('name', $key)->where('name', 'like', 'custom\_%')->first();
    }

    /**
     * The roles the owner can give, templates first and then the company's own by label.
     *
     * @return list<CompanyRoleSummary>
     */
    public function assignableSummaries(Tenant $tenant): array
    {
        return [
            ...array_map(fn (TenantRole $role): CompanyRoleSummary => new CompanyRoleSummary($role->value, $role->label(), true), $tenant->type->assignableRoles()),
            ...$this->customRoles($tenant)->map(fn (Role $role): CompanyRoleSummary => self::summary($role->name, $role->getAttribute('label')))->values()->all(),
        ];
    }

    /** How the panel names a role: a template by its own label, one of the company's by the label the owner typed. */
    public static function summary(string $name, mixed $label): CompanyRoleSummary
    {
        $template = TenantRole::tryFrom($name);

        return new CompanyRoleSummary($name, $template?->label() ?? (is_string($label) ? $label : $name), $template !== null);
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
        $assignable = $this->assignablePermissions($tenant);

        foreach ($permissions as $name) {
            isset($assignable[$name]) || throw new InvalidArgumentException("The company cannot hand out [$name].");
        }

        return array_any($permissions, fn (string $name): bool => $assignable[$name]);
    }

    /**
     * What the company can hand out now (not the owner's own, admitted by the account, of a module it has), each with
     * whether it is sensitive.
     *
     * @return array<string, bool>
     */
    public function assignablePermissions(Tenant $tenant): array
    {
        $assignable = [];

        foreach (PermissionCatalog::assignable($tenant) as $definition) {
            if (PermissionCatalog::unavailableReason($tenant, $definition) === null) {
                $assignable[$definition->name] = $definition->sensitive;
            }
        }

        return $assignable;
    }

    /** Replaces the person's role in the company with `$role`, a template or one of the company's own. */
    public function assign(User $user, Tenant $tenant, TenantRole|Role $role): void
    {
        $this->inTeam($user, $tenant, fn () => $user->syncRoles([$role instanceof Role ? $role : $role->value]));
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
     * Everyone's role in the company, a template or one of its own, by user id, in one query.
     *
     * @return array<int, CompanyRoleSummary>
     */
    public function inCompany(Tenant $tenant): array
    {
        $roles = [];

        foreach (DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.tenant_id', $tenant->id)
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->orderBy('model_has_roles.model_id')
            ->get(['model_has_roles.model_id', 'roles.name', 'roles.label']) as $row) {
            if (is_string($row->name) && is_int($row->model_id)) {
                $roles[$row->model_id] = self::summary($row->name, $row->label);
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
