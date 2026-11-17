<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Support;

use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Contracts\WorkerSection;
use App\Modules\WorkerAccess\Data\WorkerIdentity;
use App\Modules\WorkerAccess\Data\WorkerSectionLink;

/**
 * «Su evaluación NOM-035» in the person's start, while the person has a guide left in a campaign that is open: the
 * link is the one of their email (derived, never stored), so the evaluation is one tap from the start. The answers have no
 * key to the person; this only says that the invitation is still waiting.
 */
final readonly class SurveyWorkerSection implements WorkerSection
{
    public function __construct(private Companies $companies) {}

    public function for(WorkerIdentity $person): ?WorkerSectionLink
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

        return new WorkerSectionLink('survey', 'Su evaluación NOM-035', 'Es anónima', route('survey.show', $first->surveyToken()), $open->count());
    }
}
