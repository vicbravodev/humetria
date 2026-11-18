<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Support;

use App\Modules\Messaging\Data\FailureCause;
use App\Modules\Messaging\Data\InvitationReference;
use App\Modules\Messaging\Enums\DeliveryStatus;
use App\Modules\Messaging\Events\WhatsAppMessageFailed;
use App\Modules\Messaging\Models\Delivery;
use Carbon\CarbonImmutable;

/** Ends a WhatsApp message as failed (or paused) with its cause and says so, once, to whoever listens. */
final class DeliveryFailures
{
    public function __construct(private readonly RecordDeliveryEvent $record) {}

    /** @param bool $announce false when the message had already ended and whoever listens has been told. */
    public function fail(Delivery $delivery, FailureCause $cause, DeliveryStatus $status = DeliveryStatus::Failed, ?CarbonImmutable $occurredAt = null, bool $announce = true): void
    {
        $this->record->handle($delivery, $status, $occurredAt ?? CarbonImmutable::now(), $cause);

        if (! $announce) {
            return;
        }

        event(new WhatsAppMessageFailed(
            $delivery->id,
            $delivery->tenant_id,
            $delivery->created_at,
            new InvitationReference($delivery->invitation_type, $delivery->invitation_id, $delivery->source_type, $delivery->source_id),
            $cause,
        ));
    }
}
