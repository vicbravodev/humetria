<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Spatie\Permission\Models\Role;

/**
 * «Qué puede hacer cada quien» (board V3-Roles), as the company's roles really are: one column per role (the owner, the
 * templates it hands out, then its own), one row per permission of the catalog it can use, «Sí» where the role has it.
 */
final class RoleMatrix
{
    /**
     * The matrix a company shows; a union has only its owner and HR on its contract calculator, so it shows none.
     *
     * @return array{columns: list<string>, rows: list<array{label: string, cells: list<string>}>}|null
     */
    public static function for(Tenant $tenant): ?array
    {
        if ($tenant->isUnion()) {
            return null;
        }

        $templates = array_map(fn (TenantRole $role): string => $role->value, [TenantRole::Owner, ...$tenant->type->assignableRoles()]);
        $byName = Role::query()->with('permissions')->whereNull('tenant_id')->whereIn('name', $templates)->get()->keyBy('name');
        // Templates in their own order, then the company's roles by label.
        $ordered = [
            ...array_values(array_filter(array_map(fn (string $name): ?Role => $byName->get($name), $templates))),
            ...Role::query()->with('permissions')->where('tenant_id', $tenant->id)->where('name', 'like', 'custom\_%')->orderBy('label')->get()->all(),
        ];
        $columns = [];
        $held = [];

        foreach ($ordered as $role) {
            $columns[] = CompanyRoles::summary($role->name, $role->getAttribute('label'))->label;
            $held[] = self::names($role);
        }

        $rows = [];

        foreach (PermissionCatalog::all() as $permission) {
            if ($tenant->type->admits([$permission->name]) && PermissionCatalog::unavailableReason($tenant, $permission) === null) {
                $rows[] = ['label' => $permission->label, 'cells' => array_map(fn (array $names): string => in_array($permission->name, $names, true) ? 'Sí' : 'No', $held)];
            }
        }

        return ['columns' => $columns, 'rows' => $rows];
    }

    /** @return list<string> */
    private static function names(Role $role): array
    {
        $names = [];

        foreach ($role->permissions as $permission) {
            $names[] = $permission->name;
        }

        return $names;
    }
}
