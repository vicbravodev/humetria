<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Data;

/** What the owner chose in "Canales de comunicación": each channel on or off, which goes first, the WhatsApp cap, and whether the words were accepted. */
final readonly class ChannelSettingsData
{
    public function __construct(
        public bool $mailEnabled,
        public bool $whatsAppEnabled,
        public string $preferred,
        public ?int $whatsAppMonthlyCap,
        public bool $consentAccepted,
    ) {}
}
