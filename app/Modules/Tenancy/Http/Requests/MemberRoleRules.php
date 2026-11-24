<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Requests;

use App\Modules\Tenancy\Data\CompanyRoleSummary;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\CompanyRoles;
use App\Modules\Tenancy\Support\PermissionCatalog;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

final class MemberRoleRules
{
    /**
     * The roles the owner hands out from the account: the templates its type allows (never owner) and the company's own.
     *
     * @return list<In|string>
     */
    public static function role(Tenant $tenant): array
    {
        $keys = array_map(fn (CompanyRoleSummary $role): string => $role->key, resolve(CompanyRoles::class)->assignableSummaries($tenant));

        return ['required', 'string', Rule::in($keys)];
    }

    /**
     * The permissions given besides the role: only those the company can hand out (not the owner's own, not a module it
     * does not have, and for a union only compensation).
     *
     * @return array{permissions: list<string>, 'permissions.*': list<In|string>}
     */
    public static function permissions(Tenant $tenant): array
    {
        $names = [];

        foreach (PermissionCatalog::assignable($tenant) as $definition) {
            PermissionCatalog::unavailableReason($tenant, $definition) === null && $names[] = $definition->name;
        }

        return ['permissions' => ['sometimes', 'array'], 'permissions.*' => ['string', 'distinct', Rule::in($names)]];
    }
}
