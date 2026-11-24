<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Data;

final readonly class MemberSummary
{
    public function __construct(
        public int $userId,
        public string $name,
        public string $email,
        public CompanyRoleSummary $role,
    ) {}
}
