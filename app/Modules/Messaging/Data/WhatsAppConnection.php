<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Data;

use Carbon\CarbonImmutable;

/**
 * Whether WhatsApp may send: the function is on (WHATSAPP_ENABLED), the operator has not paused it (WHATSAPP_PAUSED), and the
 * automatic pause that is open, if any, with its reason and since when.
 */
final readonly class WhatsAppConnection
{
    public function __construct(
        public bool $enabled,
        public bool $pausedByOperator,
        public ?string $pauseReason,
        public ?CarbonImmutable $pausedSince,
    ) {}
}
