<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Listeners;

use App\Modules\Messaging\Actions\CreateDelivery;
use App\Modules\Messaging\Contracts\TracksDelivery;
use App\Modules\Messaging\Support\MailDeliveryStash;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use Illuminate\Notifications\Events\NotificationSending;

/**
 * Each mail of a followed notification (invitation, reminder, resend, fallback) is a message: its row exists before the mail
 * leaves. The demo company's mail does not leave (BlockDemoMail), so it is not followed.
 */
final readonly class OpenMailDelivery
{
    public function __construct(
        private CreateDelivery $create,
        private MailDeliveryStash $stash,
        private DemoCompanies $demos,
    ) {}

    public function handle(NotificationSending $event): void
    {
        if ($event->channel !== 'mail' || ! $event->notification instanceof TracksDelivery) {
            return;
        }

        $reference = $event->notification->deliveryReference();

        if ($reference === null || $this->demos->isCurrent()) {
            return;
        }

        $this->stash->put($this->create->handle('mail', $reference, null, null)->id);
    }
}
