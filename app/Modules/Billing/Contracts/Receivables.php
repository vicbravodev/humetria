<?php

declare(strict_types=1);

namespace App\Modules\Billing\Contracts;

use App\Modules\Billing\Data\CompanyPayer;
use App\Modules\Billing\Data\OverdueAccount;

/**
 * What is left to collect, for the staff's overview: what the accounts owe past due and which they are, and how many plans renew
 * soon. A statement is past due `billing.overdue_after_days` after it was issued; that day is its first day past due.
 */
interface Receivables
{
    /** What every account owes past due, in cents. */
    public function overdueCents(): int;

    /**
     * The accounts with something past due, the one overdue longest first, up to a limit.
     *
     * @return list<OverdueAccount>
     */
    public function overdueAccounts(int $limit = 5): array;

    /** How many billed plans (active or past due) renew within that many days from today; one that is cancelling ends instead. */
    public function renewingWithin(int $days): int;

    /** The account that pays the company now and what it owes past due; null when no account pays it. */
    public function payerOf(string $tenantId): ?CompanyPayer;
}
