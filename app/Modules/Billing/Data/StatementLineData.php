<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

/** One line of a statement before it is written: a discount or a credit is negative. A charge's line knows which charge it bills. */
final readonly class StatementLineData
{
    public function __construct(
        public string $code,
        public string $label,
        public int $amountCents,
        public string $explanation,
        public ?string $chargeId = null,
    ) {}
}
