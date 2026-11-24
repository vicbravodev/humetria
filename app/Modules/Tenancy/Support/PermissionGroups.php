<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Enums\Module;
use App\Modules\Tenancy\Models\Tenant;

/**
 * The switches of the roles screen, by tool: every permission the owner can hand out in this account, on where it is
 * held, off with its reason where the plan lacks its tool (a permission held of a tool the company lost counts for
 * nothing, so it shows off).
 */
final class PermissionGroups
{
    /**
     * @param  array<string, 'role'|'direct'>  $held  the permissions held, by name, and where each comes from.
     * @return list<array{module: string, label: string, permissions: list<array{name: string, label: string, description: string, sensitive: bool, enabled: bool, unavailableReason: string|null, source: 'role'|'direct'|null}>}>
     */
    public static function for(Tenant $tenant, array $held): array
    {
        $groups = [];

        foreach (PermissionCatalog::assignable($tenant) as $permission) {
            $reason = PermissionCatalog::unavailableReason($tenant, $permission);
            $source = $reason === null ? $held[$permission->name] ?? null : null;
            $groups[$permission->module] ??= ['module' => $permission->module, 'label' => Module::tryFrom($permission->module)?->label() ?? 'General', 'permissions' => []];
            $groups[$permission->module]['permissions'][] = [
                'name' => $permission->name,
                'label' => $permission->label,
                'description' => $permission->description,
                'sensitive' => $permission->sensitive,
                'enabled' => $source !== null,
                'unavailableReason' => $reason,
                'source' => $source,
            ];
        }

        return array_values($groups);
    }
}
