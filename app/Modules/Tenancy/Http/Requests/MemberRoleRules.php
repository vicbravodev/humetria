<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Requests;

use App\Modules\Tenancy\Enums\TenantRole;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

final class MemberRoleRules
{
    /**
     * The roles the owner hands out from the company: everything but owner.
     *
     * @return list<In|string>
     */
    public static function role(): array
    {
        return ['required', 'string', Rule::in([TenantRole::Hr->value, TenantRole::Viewer->value])];
    }
}
