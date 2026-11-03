<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Requests;

use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

final class MemberRoleRules
{
    /**
     * The roles the owner hands out from the account: the ones its type allows (never owner).
     *
     * @return list<In|string>
     */
    public static function role(Tenant $tenant): array
    {
        return ['required', 'string', Rule::in(array_map(fn (TenantRole $role): string => $role->value, $tenant->type->assignableRoles()))];
    }
}
