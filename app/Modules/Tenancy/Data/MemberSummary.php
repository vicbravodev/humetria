<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Data;

use App\Modules\Tenancy\Enums\TenantRole;

final readonly class MemberSummary
{
    public function __construct(
        public int $userId,
        public string $name,
        public string $email,
        public TenantRole $role,
    ) {}
}
