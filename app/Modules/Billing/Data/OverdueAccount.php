<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

/** An account with something past due: what it owes past due in cents and the days its oldest open statement is past due. */
final readonly class OverdueAccount
{
    public function __construct(
        public string $accountId,
        public string $legalName,
        public int $overdueCents,
        public int $daysPastDue,
    ) {}
}
