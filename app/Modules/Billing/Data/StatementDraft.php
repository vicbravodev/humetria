<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

use Carbon\CarbonImmutable;

/**
 * What a company owes for a month, worked out and not yet written: the preview shows it and the draft is made from it. The
 * VAT is the last line; the snapshot is everything the amounts came from.
 */
final readonly class StatementDraft
{
    /**
     * @param  list<StatementLineData>  $lines
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public string $tenantId,
        public string $accountId,
        public CarbonImmutable $periodStart,
        public CarbonImmutable $periodEnd,
        public string $currency,
        public string $catalogVersion,
        public int $vatPercent,
        public array $lines,
        public int $subtotalCents,
        public int $vatCents,
        public int $totalCents,
        public array $snapshot,
    ) {}
}
