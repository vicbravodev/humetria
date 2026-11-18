<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Support;

use App\Modules\Messaging\Contracts\WhatsAppGateway;
use App\Modules\Messaging\Data\FailureCause;
use App\Modules\Messaging\Data\OutboundMessage;
use App\Modules\Messaging\Data\SendOutcome;
use Illuminate\Support\Facades\Log;

/**
 * The gateway while WHATSAPP_ENABLED is false: the function does not exist in this deployment, so nothing leaves.
 */
final class NullWhatsAppGateway implements WhatsAppGateway
{
    public function send(OutboundMessage $message): SendOutcome
    {
        Log::info('whatsapp.send_skipped', ['template' => $message->template]);

        return SendOutcome::failed(FailureCause::Disabled);
    }
}
