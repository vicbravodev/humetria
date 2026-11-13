<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support;

use App\Modules\Engagement\Support\Results\ManagerFeedback;
use App\Modules\Telework\Contracts\PortalSection;
use App\Modules\Telework\Data\PortalIdentity;
use App\Modules\Telework\Data\PortalSectionLink;
use App\Modules\Tenancy\Contracts\Companies;

/**
 * «Retroalimentación de mi equipo» in the home of the portal, for a manager whose group is shown in the last survey that asked.
 */
final readonly class FeedbackPortalSection implements PortalSection
{
    public function __construct(private Companies $companies) {}

    public function for(PortalIdentity $person, string $token): ?PortalSectionLink
    {
        $company = $this->companies->current();
        $survey = $company->stopped('climate') && $company->stopped('pulse') ? null : ManagerSurvey::latest();

        if ($survey === null || ManagerFeedback::for($survey, $person->employeeId) === null) {
            return null;
        }

        return new PortalSectionLink('feedback', 'Retroalimentación de mi equipo', 'Lo que su equipo contestó sobre usted en la última encuesta.', route('portal.feedback.show', $token));
    }
}
