<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

/** What one WhatsApp message costs the company now (Meta's cost plus the margin), in millionths of the currency's unit. */
final readonly class MessagePrice
{
    public function __construct(public int $micros, public string $currency) {}

    /** "$0.13 MXN": at least centavos, and the micros that matter after them. */
    public function label(): string
    {
        [$whole, $fraction] = explode('.', number_format($this->micros / 1_000_000, 4, '.', ''));

        return '$'.$whole.'.'.str_pad(rtrim($fraction, '0'), 2, '0').' '.$this->currency;
    }
}
