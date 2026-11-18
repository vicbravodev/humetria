<?php

declare(strict_types=1);

namespace App\Modules\Billing\Data;

/** A month of WhatsApp for the platform: the messages, what Meta charged for them and what the companies were charged, in cents. */
final readonly class MessagingMargin
{
    public function __construct(public int $messages, public int $costCents, public int $chargedCents) {}

    public function marginCents(): int
    {
        return $this->chargedCents - $this->costCents;
    }
}
