<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Data;

final readonly class TenantData
{
    /**
     * @param  string  $rfc  Uppercase, without spaces.
     * @param  string  $slug  Lowercase, the company's address under /admin/.
     */
    public function __construct(
        public string $legalName,
        public string $tradeName,
        public string $rfc,
        public string $slug,
    ) {}
}
