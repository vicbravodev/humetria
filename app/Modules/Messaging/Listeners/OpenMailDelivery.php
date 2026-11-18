<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Listeners;

use App\Modules\Messaging\Actions\CreateDelivery;
use App\Modules\Messaging\Contracts\TracksDelivery;
use App\Modules\Messaging\Enums\DeliveryStatus;
use App\Modules\Messaging\Models\Delivery;
use App\Modules\Messaging\Support\MailDeliveryStash;
use App\Modules\Tenancy\Contracts\CurrentTenant;
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
        private CurrentTenant $tenant,
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

        // A row still in created for this invitation is a send that failed before the mail left (MarkMailSent moves the ones that
        // went out): the retry takes it up instead of leaving it behind, alive and counted as not delivered.
        $failed = Delivery::query()
            ->where('tenant_id', $this->tenant->id())
            ->where('channel', 'mail')
            ->where('invitation_type', $reference->type)
            ->where('invitation_id', $reference->id)
            ->where('status', DeliveryStatus::Created)
            ->whereNull('provider_message_id')
            ->latest('created_at')
            ->first();

        $this->stash->put($failed->id ?? $this->create->handle('mail', $reference, null, null)->id);
    }
}
