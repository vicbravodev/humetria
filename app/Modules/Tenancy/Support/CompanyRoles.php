<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Models\User;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Closure;
use Illuminate\Support\Facades\DB;
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
