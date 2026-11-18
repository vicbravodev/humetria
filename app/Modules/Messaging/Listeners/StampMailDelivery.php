<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Listeners;

use App\Modules\Messaging\Support\MailDeliveryStash;
use Illuminate\Mail\Events\MessageSending;

/**
 * Puts the id of the delivery on the message, in a header our transport turns into the tag Resend returns in every event.
 */
final readonly class StampMailDelivery
{
    public const string HEADER = 'X-Humetria-Delivery';

    public function __construct(private MailDeliveryStash $stash) {}

    public function handle(MessageSending $event): void
    {
        $id = $this->stash->take();

        if ($id !== null) {
            $event->message->getHeaders()->addTextHeader(self::HEADER, $id);
        }
    }
}
