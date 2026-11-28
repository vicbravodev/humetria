<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

/**
 * The account that pays a company now, as the console tells it beside the company: who it is, what kind, and what the account
 * (not the company alone, when a consultant pays for several) owes past due in cents and the days its oldest statement is.
 */
final readonly class CompanyPayer
{
    public function __construct(
        public string $accountId,
        public string $legalName,
        public string $kindLabel,
        public int $overdueCents,
        public int $daysPastDue,
    ) {}
}
