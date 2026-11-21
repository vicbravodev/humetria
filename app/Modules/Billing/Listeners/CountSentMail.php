<?php

declare(strict_types=1);

namespace App\Modules\Billing\Listeners;

use App\Modules\Billing\Actions\RecordUsage;
use App\Modules\Billing\Contracts\MailBag;
use App\Modules\Billing\Contracts\UsageMeter;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;

/** Counts each campaign email accepted for sending against the company that sent it (the tenant travels in the Context, also inside jobs). */
final readonly class CountSentMail
{
    public function __construct(private RecordUsage $record, private DemoCompanies $demos) {}

    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'mail' || ! $event->notification instanceof MailBag || $event->notification->mailBag() !== MailBag::CAMPAIGN) {
            return;
        }

        $tenantId = Context::get('tenant_id');

        if (! is_string($tenantId)) {
            Log::warning('usage.mail_without_company', ['notification' => $event->notification::class]);

            return;
        }

        // The demo's mail is cancelled before it leaves (BlockDemoMail), yet Laravel still says it was sent: nothing to count.
        if ($this->demos->isDemo($tenantId)) {
            return;
        }

        // Laravel gives every notification an id before it sends it; without one there is no key to count it once by.
        $key = $event->notification->id;

        if ($key === null) {
            Log::warning('usage.mail_without_id', ['notification' => $event->notification::class]);

            return;
        }

        $this->record->handle($tenantId, UsageMeter::EMAIL_CAMPAIGN, 1, $key, fromHold: true);
    }
}
