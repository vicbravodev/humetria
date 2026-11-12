<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

/** One line of a quote. A discount is a negative amount. The explanation says how it was reached, in words. */
final readonly class QuoteLine
{
    public function __construct(public string $code, public string $label, public int $amountCents, public string $explanation) {}
}
