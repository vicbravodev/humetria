<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Events;

use App\Modules\Messaging\Data\FailureCause;
use App\Modules\Messaging\Data\InvitationReference;
use Carbon\CarbonImmutable;

/** A WhatsApp message did not go out or did not arrive: Billing releases its hold and the invitation modules may fall back to mail. */
final readonly class WhatsAppMessageFailed
{
    public function __construct(
        public string $deliveryId,
        public string $tenantId,
        public CarbonImmutable $createdAt,
        public InvitationReference $invitation,
        public FailureCause $cause,
    ) {}

    public function fallsBackToMail(): bool
    {
        return $this->cause->fallsBackToMail();
    }
}
