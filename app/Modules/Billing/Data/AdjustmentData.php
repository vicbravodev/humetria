<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

/** What the console sends to correct an issued statement: what it is for, how much without VAT (a credit is negative) and why. */
final readonly class AdjustmentData
{
    public function __construct(public string $description, public int $amountCents, public string $reason) {}
}
