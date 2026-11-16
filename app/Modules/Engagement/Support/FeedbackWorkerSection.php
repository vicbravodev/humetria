<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support;

use App\Modules\Engagement\Support\Results\ManagerFeedback;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Contracts\WorkerSection;
use App\Modules\WorkerAccess\Data\WorkerIdentity;
use App\Modules\WorkerAccess\Data\WorkerSectionLink;

/**
 * «Retroalimentación de mi equipo» in the person's start, for a manager whose group is shown in the last survey that asked.
 */
final readonly class FeedbackWorkerSection implements WorkerSection
{
    public function __construct(private Companies $companies) {}

    public function for(WorkerIdentity $person): ?WorkerSectionLink
    {
        $company = $this->companies->current();
        $survey = $company->stopped('climate') && $company->stopped('pulse') ? null : ManagerSurvey::latest();

        if ($survey === null || ManagerFeedback::for($survey, $person->employeeId) === null) {
            return null;
        }

        return new WorkerSectionLink('feedback', 'Retroalimentación de mi equipo', 'Lo que su equipo contestó sobre usted en la última encuesta.', route('portal.feedback.show', $company->slug));
    }
}
