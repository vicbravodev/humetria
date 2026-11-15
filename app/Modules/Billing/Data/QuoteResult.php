<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

/**
 * What a company pays for a period, in cents of the currency, with the lines that explain it. When the size is beyond the
 * table it requires a quotation: then there are no lines and no amounts, only the reason.
 */
final readonly class QuoteResult
{
    /**
     * @param  list<QuoteLine>  $lines
     */
    public function __construct(
        public string $currency,
        public string $catalogVersion,
        public array $lines,
        public bool $requiresQuotation,
        public ?int $subtotalCents,
        public ?int $vatCents,
        public ?int $totalCents,
        public string $explanation,
    ) {}
}
