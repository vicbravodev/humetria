<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Support;

use App\Modules\Campaigns\Enums\CampaignKind;
use App\Modules\Campaigns\Enums\SurveyEmail;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Campaigns\Notifications\SurveyInvitation;
use App\Modules\Instruments\Contracts\Instruments;
use App\Modules\Messaging\Data\InvitationReference;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\CompanySummary;
use App\Modules\WorkerAccess\Contracts\WorkerLinks;
use LogicException;

/**
 * Builds the notification that carries a person's survey link: the one place that knows what an invitation is made of, used by
 * the job that sends them and by the fallback to mail. What is the same for everyone in the company (its name and contact, the
 * entrance of the people) is read once per instance. The company in the current request is the one it builds for.
 */
final class InvitationComposer
{
    private ?CompanySummary $company = null;

    private ?string $contact = null;

    private ?string $home = null;

    /** @var array<string, string> */
    private array $centers = [];

    public function __construct(private readonly Companies $companies, private readonly WorkCenters $workCenters, private readonly WorkerLinks $links, private readonly Instruments $instruments) {}

    /** Which email a person gets: a reminder to whoever already has their link, otherwise the one the kind of campaign asks for. */
    public static function emailFor(Campaign $campaign, bool $reminder): SurveyEmail
    {
        if ($campaign->kind === CampaignKind::Nom035Trauma) {
            return SurveyEmail::Trauma;
        }

        if ($campaign->kind === CampaignKind::SeatingSurvey) {
            return SurveyEmail::Seating;
        }

        return $reminder ? SurveyEmail::Reminder : SurveyEmail::Invitation;
    }

    /** `$participant` comes with its pending guides loaded (`instruments`). */
    public function compose(Campaign $campaign, Participant $participant, RosterEntry $person, string $token, SurveyEmail $kind): SurveyInvitation
    {
        $this->company ??= $this->companies->current();
        $this->contact ??= $this->companies->contact();
        $this->home ??= $this->links->home();
        $center = $this->centers[$campaign->work_center_id] ??= $this->workCenters->profile($campaign->work_center_id)->name ?? '';
        $closesOn = $campaign->closes_on ?? throw new LogicException("Campaign [{$campaign->id}] has no last day.");

        return new SurveyInvitation(
            $kind,
            $token,
            $person->name,
            $this->company,
            $this->contact,
            $center,
            $closesOn,
            $this->minutes($participant),
            $campaign->groupMinimum(),
            $this->home,
            new InvitationReference(InvitationReference::CAMPAIGN_PARTICIPANT, $participant->id, InvitationReference::CAMPAIGN, $campaign->id),
        );
    }

    /** How long the guides the person has left take together: a reminder never counts what they already answered. */
    private function minutes(Participant $participant): int
    {
        return $participant->instruments->sum(fn (ParticipantInstrument $guide): int => $this->instruments->definition($guide->instrument)->minutes);
    }
}
