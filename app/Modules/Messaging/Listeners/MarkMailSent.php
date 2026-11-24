<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Listeners;

use App\Modules\Messaging\Enums\DeliveryStatus;
use App\Modules\Messaging\Models\Delivery;
use App\Modules\Messaging\Support\RecordDeliveryEvent;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Str;

/**
 * The mail left our system: its message passes to `sent`. A followed message still in `created` is one whose send failed, so the
 * row of a failed attempt is told apart from one that went out, and the retry reuses it (OpenMailDelivery).
 */
final readonly class MarkMailSent
{
    public function __construct(private RecordDeliveryEvent $record) {}

    public function handle(MessageSent $event): void
    {
        $id = $event->message->getHeaders()->get(StampMailDelivery::HEADER)?->getBodyAsString();
        $delivery = $id !== null && Str::isUuid($id) ? Delivery::query()->where('channel', 'mail')->find($id) : null;

        if ($delivery !== null) {
            $this->record->handle($delivery, DeliveryStatus::Sent, CarbonImmutable::now());
        }
    }
}
