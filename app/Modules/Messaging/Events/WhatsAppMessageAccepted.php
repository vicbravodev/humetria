<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Events;

/** The provider accepted the message (or an unresolved one was settled as used): Billing counts it. Raised by the job, the webhook or the reconciliation. */
final readonly class WhatsAppMessageAccepted
{
    public function __construct(public string $deliveryId, public string $tenantId) {}
}
