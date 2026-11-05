<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support\Writing;

use App\Modules\Telework\Enums\DocumentKind;
use App\Support\Writing\WrittenTemplate;

/** Which NOM-037 documents are written with questions (decisión A3), and with which template; the engine is App\Support\Writing. */
final readonly class Templates
{
    /** The template a kind is written with, or null for a kind that is only uploaded (the inspection report). */
    public function template(DocumentKind $kind): ?WrittenTemplate
    {
        return match ($kind) {
            DocumentKind::Policy => new PolicyTemplate,
            DocumentKind::RiskInformation => new RiskTemplate,
            DocumentKind::ImplementationProcess => new ImplementationTemplate,
            DocumentKind::MaintenanceProgram => new MaintenanceTemplate,
            DocumentKind::Reversibility => new ReversibilityTemplate,
            DocumentKind::FamilyViolence => new FamilyViolenceTemplate,
            DocumentKind::CommitteeParticipation => new CommitteeTemplate,
            DocumentKind::InspectionReport => null,
        };
    }
}
