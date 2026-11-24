<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Support;

use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Messaging\Contracts\InvitationLinks;
use App\Modules\Messaging\Data\InvitationReference;
use App\Modules\Messaging\Data\WhatsAppInvitation;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use Carbon\CarbonImmutable;

/**
 * What the WhatsApp job needs to send a participant's survey link, read when it runs (the queue never carries it). Null when the
 * invitation no longer waits: nothing pending, the campaign closed or past its last day, or the person left the roster.
 */
final readonly class CampaignInvitationLinks implements InvitationLinks
{
    /** The template this module's invitation is registered under in Messaging (TemplateRegistry, kept in step by its test). */
    private const string TEMPLATE = 'survey_invite_v1';

    public function __construct(private Roster $roster, private Companies $companies) {}

    public function type(): string
    {
        return InvitationReference::CAMPAIGN_PARTICIPANT;
    }

    public function whatsApp(string $invitationId): ?WhatsAppInvitation
    {
        $participant = Participant::query()->with('campaign')->find($invitationId);
        $campaign = $participant?->campaign;

        if ($participant === null || $campaign === null || $campaign->status !== CampaignStatus::Active || $campaign->closes_on === null || CampaignClock::hasPassed($campaign->closes_on)) {
            return null;
        }

        if (! $participant->instruments()->where('status', InstrumentStatus::Pending)->exists()) {
            return null;
        }

        $person = $this->roster->find($participant->employee_id);

        if ($person === null || ! $person->active || $person->mobile === null) {
            return null;
        }

        $lastDay = CarbonImmutable::parse($campaign->closes_on->format('Y-m-d'), config()->string('app.display_timezone'))->isoFormat('D [de] MMMM');

        return new WhatsAppInvitation(
            $person->mobile,
            self::TEMPLATE,
            [$this->companies->current()->tradeName, $lastDay],
            $participant->surveyToken(),
        );
    }
}
