<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Enums;

/**
 * What kind of account a tenant is: a company that complies with the labor norms, or a union
 * that only costs a collective-contract proposal. It is chosen when the account is created and never changes.
 */
enum TenantType: string
{
    case Company = 'company';
    case Union = 'union';

    /** Whether the account can have someone in that role: the owner always, the rest as assignableRoles() says. */
    public function allows(TenantRole $role): bool
    {
        return $role === TenantRole::Owner || in_array($role, $this->assignableRoles(), true);
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
}
