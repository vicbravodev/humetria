<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

use Carbon\CarbonImmutable;

/** What the console sends to record a transfer: how much, when, its reference, and which statements it settles and for how much each. */
final readonly class PaymentData
{
    /**
     * @param  array<string, int>  $allocations  cents applied, by statement id; what is left over stays unapplied.
     */
    public function __construct(
        public int $amountCents,
        public CarbonImmutable $paidOn,
        public string $reference,
        public ?string $note,
        public array $allocations = [],
    ) {}
}
