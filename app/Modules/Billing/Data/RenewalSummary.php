<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

/**
 * What one run of the monthly renewal did: drafts it made, drafts that were already there, plans it ended, the companies it could
 * not draft (with why), and the drafts and their total by paying account, so a consultant is reviewed once.
 */
final readonly class RenewalSummary
{
    /**
     * @param  list<array{tenant_id: string, reason: string}>  $skipped
     * @param  list<array{account_id: string, drafts: int, total_cents: int}>  $byAccount
     */
    public function __construct(
        public string $period,
        public int $drafted,
        public int $alreadyDrafted,
        public int $ended,
        public array $skipped,
        public array $byAccount,
    ) {}
}
