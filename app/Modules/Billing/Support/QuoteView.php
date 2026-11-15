<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Data\QuoteLine;
use App\Modules\Billing\Data\QuoteResult;

/** A quote as the screen shows it: every amount already written as pesos, so no page does arithmetic on money. */
final class QuoteView
{
    /**
     * @return array{requires_quotation: bool, explanation: string, lines: list<array{label: string, amount: string, explanation: string}>, subtotal: string|null, subtotal_cents: int|null, vat: string|null, total: string|null}
     */
    public static function from(QuoteResult $quote): array
    {
        return [
            'requires_quotation' => $quote->requiresQuotation,
            'explanation' => $quote->explanation,
            'lines' => array_map(fn (QuoteLine $line): array => [
                'label' => $line->label,
                'amount' => Money::format($line->amountCents),
                'explanation' => $line->explanation,
            ], $quote->lines),
            'subtotal' => $quote->subtotalCents === null ? null : Money::format($quote->subtotalCents),
            'subtotal_cents' => $quote->subtotalCents,
            'vat' => $quote->vatCents === null ? null : Money::format($quote->vatCents),
            'total' => $quote->totalCents === null ? null : Money::format($quote->totalCents),
        ];
    }
}
