<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Listeners;

use App\Modules\Messaging\Data\InvitationReference;
use App\Modules\Messaging\Events\WhatsAppMessageFailed;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use App\Modules\WorkerAccess\Actions\CreateAccessLink;
use App\Modules\WorkerAccess\Models\WorkerAccessLink;
use App\Modules\WorkerAccess\Notifications\WorkerInvitation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * When the WhatsApp of a portal invitation fails for good (rule 9) the person gets the same link by mail, while it still opens and
 * they have an address. Unlike a survey link it is not rotated: the token is rebuilt from the link (WorkerAccessLink::tokenFor),
 * the WhatsApp never reached the person, and a retry mails the same link again, so nobody holds two links that open. The demo
 * already mailed it with the WhatsApp (ChannelPicker sends both there), so it gets no second mail.
 */
final class FallBackToMail implements ShouldQueue
{
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function handle(WhatsAppMessageFailed $event): void
    {
        if (! $event->fallsBackToMail() || $event->invitation->type !== InvitationReference::WORKER_ACCESS_LINK) {
            return;
        }

        if (resolve(DemoCompanies::class)->isDemo($event->tenantId)) {
            Log::info('worker_access.fallback_unneeded', ['link_id' => $event->invitation->id]);

            return;
        }

        $company = resolve(Companies::class)->enter($event->tenantId);
        $link = WorkerAccessLink::findPendingById($event->invitation->id);
        $person = $link === null ? null : resolve(Roster::class)->find($link->employee_id);
        // Mail only: the person's mobile is what just failed. The invitation is essential mail, as when it is issued.
        $email = $person === null || ! $person->active ? null : $person->email;

        if ($link === null || $person === null || $email === null) {
            Log::info('worker_access.fallback_unavailable', ['link_id' => $event->invitation->id, 'cause' => $event->cause->value]);

            return;
        }

        Notification::route('mail', $email)->notify(new WorkerInvitation(CreateAccessLink::address($company->slug, $link->token()), $person->name, $company, $event->invitation));
        Log::info('worker_access.fallback_sent', ['link_id' => $link->id, 'cause' => $event->cause->value]);
    }
}
