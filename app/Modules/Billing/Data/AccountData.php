<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

use App\Modules\Billing\Enums\AccountKind;

/** What the console sends to create or change a payer account. */
final readonly class AccountData
{
    public function __construct(
        public AccountKind $kind,
        public string $legalName,
        public string $rfc,
        public string $contactName,
        public string $contactEmail,
        public ?string $taxRegime = null,
        public ?string $postalCode = null,
        public ?string $cfdiUse = null,
        public ?string $contactPhone = null,
        public ?string $notes = null,
    ) {}
}
