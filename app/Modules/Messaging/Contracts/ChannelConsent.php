<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Contracts;

/**
 * May this company write to this phone through this channel. For WhatsApp: the function is available, the owner accepted the
 * current words, and the phone has no opt-out for the company or for everyone.
 */
interface ChannelConsent
{
    public function canSend(string $tenantId, string $channel, string $phone): bool;
}
