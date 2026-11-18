<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Listeners;

use App\Modules\Billing\Contracts\MailQuota;
use App\Modules\Engagement\Enums\InvitationStatus;
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
use Throwable;

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

        // A retry of the queue (the mail failed after this already held, rotated and marked): the state says it was done, so it
        // reuses the link and the hold instead of taking another of each. The WhatsApp link never reached the person (that is why
        // the mail takes over), so rotating it loses nothing.
        $done = $invitation->invited_via === 'mail' && $invitation->token_version > 0;

        // The hold goes first: if the mail does not fit nothing was written, and the person keeps the link that went by WhatsApp.
        $token = $done ? $invitation->surveyToken() : DB::transaction(function () use ($invitation): ?string {
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

    /** The tries ran out and the mail never went out: the person is left as failed, which RH sees and can resend by mail. */
    public function failed(WhatsAppMessageFailed $event, Throwable $exception): void
    {
        if (! $event->fallsBackToMail() || $event->invitation->type !== InvitationReference::ENGAGEMENT_INVITATION) {
            return;
        }

        resolve(Companies::class)->enter($event->tenantId);
        Invitation::query()->whereKey($event->invitation->id)->update(['invitation_status' => InvitationStatus::Failed]);

        // The class only: a mail error's message can carry the address.
        Log::warning('engagement.fallback_failed', ['invitation_id' => $event->invitation->id, 'exception' => $exception::class]);
    }

    private function unavailable(WhatsAppMessageFailed $event): void
    {
        Log::info('engagement.fallback_unavailable', ['invitation_id' => $event->invitation->id, 'cause' => $event->cause->value]);
    }
}
