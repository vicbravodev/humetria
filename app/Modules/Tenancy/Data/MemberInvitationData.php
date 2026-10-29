<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Data;

use App\Modules\Tenancy\Enums\TenantRole;

final readonly class MemberInvitationData
{
    /**
     * @param  string  $email  Lowercase, without spaces.
     * @param  TenantRole  $role  hr or viewer: the owner is invited from the superadmin console.
     */
    public function __construct(
        public string $name,
        public string $email,
        public TenantRole $role,
    ) {}
}
