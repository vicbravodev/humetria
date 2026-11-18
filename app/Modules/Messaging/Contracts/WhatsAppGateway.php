<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Contracts;

use App\Modules\Messaging\Data\OutboundMessage;
use App\Modules\Messaging\Data\SendOutcome;

/**
 * Sends one template message to WhatsApp. Never throws for a provider failure: it answers with a failed outcome.
 */
interface WhatsAppGateway
{
    public function send(OutboundMessage $message): SendOutcome;
}
