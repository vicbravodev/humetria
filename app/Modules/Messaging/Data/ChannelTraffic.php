<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Data;

/** How many messages of one channel (mail, whatsapp) went out, how many arrived (read included), were read, or were rejected. */
final readonly class ChannelTraffic
{
    public function __construct(
        public string $channel,
        public int $total,
        public int $delivered,
        public int $read,
        public int $rejected,
    ) {}
}
