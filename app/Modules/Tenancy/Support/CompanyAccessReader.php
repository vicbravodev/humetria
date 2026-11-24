<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Models\User;
use App\Modules\Tenancy\Contracts\CompanyAccess;
use App\Modules\Tenancy\Data\AccessOverview;
use App\Modules\Tenancy\Data\AreaAccess;
use App\Modules\Tenancy\Data\RoleCount;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;

final readonly class CompanyAccessReader implements CompanyAccess
{
    public function __construct(private CompanyRoles $roles) {}

    public function overview(string $tenantId): AccessOverview
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $roles = [];

        foreach (DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.tenant_id', $tenantId)
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->groupBy('roles.name', 'roles.label')
            ->get(['roles.name', 'roles.label', DB::raw('count(*) as people')]) as $row) {
            if (is_string($row->name) && is_int($row->people)) {
                $roles[] = [CompanyRoles::summary($row->name, $row->label), $row->people];
            }
        }

        // The templates in their own order, then the company's own by label.
        $order = array_flip(array_map(fn (TenantRole $role): string => $role->value, TenantRole::cases()));
        usort($roles, fn (array $a, array $b): int => [$order[$a[0]->key] ?? count($order), $a[0]->label] <=> [$order[$b[0]->key] ?? count($order), $b[0]->label]);

        return new AccessOverview(
            $this->roles->ownRoles($tenant)->count(),
            config()->integer('tenancy.max_custom_roles'),
            array_map(fn (array $role): RoleCount => new RoleCount($role[0]->label, $role[1]), $roles),
            DB::table('model_has_permissions')->where('tenant_id', $tenantId)->where('model_type', (new User)->getMorphClass())->distinct()->count('model_id'),
        );
    }

    public function areasOf(string $tenantId, int $userId): array
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $held = $this->roles->effectivePermissions(User::query()->findOrFail($userId), $tenant);
        $areas = [];

        foreach (PermissionAreas::all() as $area) {
            $permissions = [...array_merge(...array_column(array_values($area['levels']), 'permissions')), ...array_keys($area['extras'])];

            // An area of a tool the plan does not include is not the person's to have.
            if (array_all($permissions, fn (string $name): bool => ($definition = PermissionCatalog::find($name)) !== null && PermissionCatalog::unavailableReason($tenant, $definition) !== null)) {
                continue;
            }

            $level = 'Sin acceso';

            foreach ($area['levels'] as $candidate) {
                array_all($candidate['permissions'], fn (string $name): bool => isset($held[$name])) && $level = $candidate['label'];
            }

            $areas[] = new AreaAccess($area['label'], $level, array_values(array_filter($area['extras'], fn (string $name): bool => isset($held[$name]), ARRAY_FILTER_USE_KEY)));
        }

        return $areas;
    }
}
