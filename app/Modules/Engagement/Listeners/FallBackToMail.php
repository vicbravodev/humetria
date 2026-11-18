<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Listeners;

use App\Modules\Billing\Contracts\MailQuota;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Support\InvitationComposer;
use App\Modules\Engagement\Support\SurveyClock;
use App\Modules\Messaging\Contracts\ChannelPicker;
use App\Modules\Messaging\Data\InvitationReference;
use App\Modules\Messaging\Events\WhatsAppMessageFailed;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * When WhatsApp fails for good (rule 9) the person gets their link by mail, if the company has mail on, the person has an address
 * and there is mail quota. The link is rotated first, so the one that went by WhatsApp stops working and nobody has two live links
 * for one invitation. Rotation, quota and channel are one transaction; the mail goes out after it commits.
 */
final class FallBackToMail implements ShouldQueue
{
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function handle(WhatsAppMessageFailed $event): void
    {
        if (! $event->fallsBackToMail() || $event->invitation->type !== InvitationReference::ENGAGEMENT_INVITATION) {
            return;
        }

        resolve(Companies::class)->enter($event->tenantId);

        $invitation = Invitation::query()->with('survey')->find($event->invitation->id);
        $survey = $invitation?->survey;

        if ($invitation === null || $survey === null || $invitation->answered || $invitation->cancelled
            || $survey->status !== SurveyStatus::Active || $survey->closes_on === null || SurveyClock::hasPassed($survey->closes_on)) {
            $this->unavailable($event);

            return;
        }

        $person = resolve(Roster::class)->find($invitation->employee_id);
        // Mail only: the person's mobile is what just failed, so the picker is asked without it.
        $mail = $person === null || ! $person->active ? [] : resolve(ChannelPicker::class)->route($person->email, null);

        if ($person === null || ! isset($mail['mail'])) {
            $this->unavailable($event);

            return;
        }

        // The hold goes first: if the mail does not fit nothing was written, and the person keeps the link that went by WhatsApp.
        $token = DB::transaction(function () use ($invitation): ?string {
            if (! resolve(MailQuota::class)->tryReserve(1)) {
                return null;
            }

            $token = $invitation->rotateToken();
            $invitation->update(['invited_via' => 'mail']);

            return $token;
        });

        if ($token === null) {
            Log::info('engagement.fallback_without_quota', ['invitation_id' => $invitation->id]);

            return;
        }

        Notification::routes(['mail' => $mail['mail']])->notify(resolve(InvitationComposer::class)->compose($invitation, $survey, $person, $token, false));
        Log::info('engagement.fallback_sent', ['invitation_id' => $invitation->id, 'cause' => $event->cause->value]);
    }

    private function unavailable(WhatsAppMessageFailed $event): void
    {
        Log::info('engagement.fallback_unavailable', ['invitation_id' => $event->invitation->id, 'cause' => $event->cause->value]);
    }
}
