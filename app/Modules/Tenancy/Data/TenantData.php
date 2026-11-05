<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Data;

use App\Modules\Tenancy\Enums\TenantType;

final readonly class TenantData
{
    /**
     * @param  string  $rfc  Uppercase, without spaces.
     * @param  string  $slug  Lowercase, the company's address under /admin/.
     * @param  TenantType  $type  Only read when the account is created: it never changes after.
     */
    public function __construct(
        public string $legalName,
        public string $tradeName,
        public string $rfc,
        public string $slug,
        public TenantType $type = TenantType::Company,
    ) {}
}
