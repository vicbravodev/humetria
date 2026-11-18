<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Listeners;

use App\Modules\Billing\Contracts\MailQuota;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Support\CampaignClock;
use App\Modules\Campaigns\Support\InvitationComposer;
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
        if (! $event->fallsBackToMail() || $event->invitation->type !== InvitationReference::CAMPAIGN_PARTICIPANT) {
            return;
        }

        resolve(Companies::class)->enter($event->tenantId);

        $participant = Participant::query()->with('campaign')->find($event->invitation->id);
        $campaign = $participant?->campaign;

        if ($participant === null || $campaign === null || ! $this->waits($participant, $campaign->status, $campaign->closes_on)) {
            $this->unavailable($event);

            return;
        }

        $person = resolve(Roster::class)->find($participant->employee_id);
        // Mail only: the person's mobile is what just failed, so the picker is asked without it.
        $mail = $person === null || ! $person->active ? [] : resolve(ChannelPicker::class)->route($person->email, null);

        if ($person === null || ! isset($mail['mail'])) {
            $this->unavailable($event);

            return;
        }

        $participant->load(['instruments' => fn ($guides) => $guides->where('status', InstrumentStatus::Pending)]);

        // The hold goes first: if the mail does not fit nothing was written, and the person keeps the link that went by WhatsApp.
        $token = DB::transaction(function () use ($participant): ?string {
            if (! resolve(MailQuota::class)->tryReserve(1)) {
                return null;
            }

            $token = $participant->rotateToken();
            $participant->update(['invited_via' => 'mail']);

            return $token;
        });

        if ($token === null) {
            Log::info('campaign.fallback_without_quota', ['participant_id' => $participant->id]);

            return;
        }

        Notification::routes(['mail' => $mail['mail']])->notify(
            resolve(InvitationComposer::class)->compose($campaign, $participant, $person, $token, InvitationComposer::emailFor($campaign, false)),
        );
        Log::info('campaign.fallback_sent', ['participant_id' => $participant->id, 'cause' => $event->cause->value]);
    }

    private function waits(Participant $participant, CampaignStatus $status, ?\Carbon\CarbonImmutable $closesOn): bool
    {
        return $status === CampaignStatus::Active
            && $closesOn !== null
            && ! CampaignClock::hasPassed($closesOn)
            && $participant->instruments()->where('status', InstrumentStatus::Pending)->exists();
    }

    private function unavailable(WhatsAppMessageFailed $event): void
    {
        Log::info('campaign.fallback_unavailable', ['participant_id' => $event->invitation->id, 'cause' => $event->cause->value]);
    }
}
