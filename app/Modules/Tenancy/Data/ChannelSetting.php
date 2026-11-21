<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Data;

/**
 * How a company has one channel: on or off, its place in the order (1 goes first), the version of the words accepted (WhatsApp) and
 * the owner's monthly cap of messages.
 */
final readonly class ChannelSetting
{
    public function __construct(
        public string $channel,
        public bool $enabled,
        public int $preference,
        public ?string $consentVersion,
        public ?int $monthlyCap,
    ) {}
}
