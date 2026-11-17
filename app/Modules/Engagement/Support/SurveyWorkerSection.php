<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support;

use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Contracts\WorkerSection;
use App\Modules\WorkerAccess\Data\WorkerIdentity;
use App\Modules\WorkerAccess\Data\WorkerSectionLink;

/**
 * «Encuesta de clima» or «Pulso» in the person's start, while the person has an invitation to an open survey they have
 * not answered: the link is the one of their email (derived, never stored). Nothing here leads to the answers.
 */
final readonly class SurveyWorkerSection implements WorkerSection
{
    public function __construct(private Companies $companies) {}

    public function for(WorkerIdentity $person): ?WorkerSectionLink
    {
        $open = Invitation::query()
            ->where('employee_id', $person->employeeId)
            ->whereNotNull('token_hash')
            ->where('answered', false)
            ->where('cancelled', false)
            ->whereHas('survey', fn ($survey) => $survey->where('status', SurveyStatus::Active)->whereNotNull('closes_on'))
            ->with('survey')
            ->oldest()
            ->get()
            ->filter(fn (Invitation $invitation): bool => ($closesOn = $invitation->survey?->closes_on) !== null && ! SurveyClock::hasPassed($closesOn))
            ->values();

        // The company is read only when there is something to show: most people have no open survey.
        if ($open->isNotEmpty()) {
            $company = $this->companies->current();
            $open = $open->reject(fn (Invitation $invitation): bool => ($kind = $invitation->survey?->kind) === null || $company->stopped($kind->value))->values();
        }

        $first = $open->first();

        if ($first === null) {
            return null;
        }

        $label = $first->survey?->kind === SurveyKind::Pulse ? 'Pulso' : 'Encuesta de clima';

        return new WorkerSectionLink('engagement', $label, 'Cuéntenos cómo está su trabajo', route('engagement.survey.show', $first->surveyToken()), $open->count());
    }
}
