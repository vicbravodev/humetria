<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Enums;

use App\Modules\Tenancy\Support\PermissionCatalog;
use Spatie\Permission\Models\Role;

/**
 * What kind of account a tenant is: a company that complies with the labor norms, or a union
 * that only costs a collective-contract proposal. It is chosen when the account is created and never changes.
 */
enum TenantType: string
{
    case Company = 'company';
    case Union = 'union';

    /**
     * Whether the account can have someone in that role: a template as assignableRoles() says (the owner always), a role of
     * its own when every permission in it is one the account admits.
     */
    public function allows(TenantRole|Role $role): bool
    {
        if ($role instanceof Role) {
            return ($template = TenantRole::tryFrom($role->name)) !== null ? $this->allows($template) : $this->admits($this->namesOf($role));
        }

        return $role === TenantRole::Owner || in_array($role, $this->assignableRoles(), true);
    }

    /**
     * Whether every permission is one this account can hold: any in a company, only those meant for a union in a union.
     *
     * @param  list<string>  $permissions
     */
    public function admits(array $permissions): bool
    {
        return $this === self::Company || array_all($permissions, fn (string $name): bool => PermissionCatalog::find($name)?->unionAllowed === true);
    }

    /**
     * The roles its owner can hand out. A union has no viewer, committee or complaints handler: only the owner and HR.
     *
     * @return list<TenantRole>
     */
    public function assignableRoles(): array
    {
        return match ($this) {
            self::Company => [TenantRole::Hr, TenantRole::Viewer, TenantRole::Committee, TenantRole::Complaints],
            self::Union => [TenantRole::Hr],
        };
    }

    /** @return list<string> */
    private function namesOf(Role $role): array
    {
        $names = [];

        foreach ($role->permissions as $permission) {
            $names[] = $permission->name;
        }

        return $names;
    }
}
