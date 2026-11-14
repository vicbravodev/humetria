<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Support;

use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Telework\Contracts\PortalSection;
use App\Modules\Telework\Data\PortalIdentity;
use App\Modules\Telework\Data\PortalSectionLink;
use App\Modules\Tenancy\Contracts\Companies;

/**
 * «Su evaluación NOM-035» in the home of the portal, while the person has a guide left in a campaign that is open: the
 * link is the one of their email (derived, never stored), so the evaluation is one tap from the portal. The answers have no
 * key to the person; this only says that the invitation is still waiting.
 */
final readonly class SurveyPortalSection implements PortalSection
{
    public function __construct(private Companies $companies) {}

    public function for(PortalIdentity $person, string $token): ?PortalSectionLink
    {
        $open = Participant::query()
            ->where('employee_id', $person->employeeId)
            ->whereNotNull('token_hash')
            ->whereHas('campaign', fn ($campaign) => $campaign->where('status', CampaignStatus::Active)->whereNotNull('closes_on'))
            ->whereHas('instruments', fn ($instruments) => $instruments->where('status', InstrumentStatus::Pending))
            ->with('campaign')
            ->oldest()
            ->get()
            ->filter(fn (Participant $participant): bool => ($closesOn = $participant->campaign?->closes_on) !== null && ! CampaignClock::hasPassed($closesOn))
            ->values();

        // The company is read only when there is something to show: most people have no open evaluation.
        $first = $open->first();

        if ($first === null || $this->companies->current()->stopped('nom035')) {
            return null;
        }

        return new PortalSectionLink('survey', 'Su evaluación NOM-035', 'Conteste la evaluación de su centro de trabajo. Sus respuestas sobre su trabajo son anónimas.', route('survey.show', $first->surveyToken()), $open->count());
    }
}
