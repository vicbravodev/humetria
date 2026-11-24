<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Data;

/**
 * The messages of one channel for one campaign or survey, already under the minimum of rule 10: a channel with fewer messages than
 * the minimum shows none of its numbers; one whose undelivered are fewer than it shows the total and "fewer than" instead of them.
 * Never a row per person.
 */
final readonly class ChannelDeliveryTally
{
    public function __construct(
        public string $channel,
        public ?int $total,
        public ?int $delivered,
        public ?int $undelivered,
        public bool $undeliveredBelowMinimum,
    ) {}

    /** @return array{channel: string, total: int|null, delivered: int|null, undelivered: int|null, undeliveredBelowMinimum: bool} */
    public function toArray(): array
    {
        return ['channel' => $this->channel, 'total' => $this->total, 'delivered' => $this->delivered, 'undelivered' => $this->undelivered, 'undeliveredBelowMinimum' => $this->undeliveredBelowMinimum];
    }
}
