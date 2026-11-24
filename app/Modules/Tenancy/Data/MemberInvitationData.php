<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Data;

final readonly class MemberInvitationData
{
    /**
     * @param  string  $email  Lowercase, without spaces.
     * @param  string  $role  The key of a template the account hands out or of a role of the company's own: never the owner,
     *                        who is invited from the superadmin console.
     * @param  list<string>  $permissions  Given besides the role.
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $role,
        public array $permissions = [],
    ) {}
}
