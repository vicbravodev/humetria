<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Contracts;

use App\Modules\Messaging\Data\OutboundMessage;
use App\Modules\Messaging\Data\SendOutcome;

/**
 * Sends one template message to WhatsApp, or a service text. Never throws for a provider failure: it answers with a failed outcome.
 */
interface WhatsAppGateway
{
    public function send(OutboundMessage $message): SendOutcome;

    /**
     * Sends a free text of service: the answer to a message the person just wrote, valid inside the 24-hour window that message
     * opens (not a template, not metered, not charged to any company). Same promise: a failure is an outcome, never an exception.
     */
    public function sendText(string $to, string $body): SendOutcome;
}
