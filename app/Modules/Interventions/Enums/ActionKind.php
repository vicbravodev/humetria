<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Enums;

/**
 * The kinds of action Humetría offers (criterio C-31). The catalog is ours, not the DOF's: it comes from the texts of Tablas 4
 * and 7 and of 8.1, 8.2 and 8.5. Each kind has the level it usually belongs to; `Other` can be of the first or second.
 */
enum ActionKind: string
{
    case PolicyReview = 'policy_review';
    case PolicyDissemination = 'policy_dissemination';
    case WorkOrganization = 'work_organization';
    case ViolencePrevention = 'violence_prevention';
    case AwarenessCampaign = 'awareness_campaign';
    case Training = 'training';
    case SocialSupport = 'social_support';
    case SpecificAssessment = 'specific_assessment';
    case ClinicalCare = 'clinical_care';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PolicyReview => 'Revisión de la política de prevención',
            self::PolicyDissemination => 'Difusión y refuerzo de la política',
            self::WorkOrganization => 'Organización del trabajo',
            self::ViolencePrevention => 'Prevención de la violencia laboral',
            self::AwarenessCampaign => 'Campaña de sensibilización',
            self::Training => 'Capacitación o información',
            self::SocialSupport => 'Apoyo social',
            self::SpecificAssessment => 'Evaluación específica',
            self::ClinicalCare => 'Atención clínica o terapéutica',
            self::Other => 'Otra acción',
        };
    }

    /** The level the kind belongs to; `Other` has none of its own. */
    public function defaultLevel(): ?ActionLevel
    {
        return match ($this) {
            self::PolicyReview, self::PolicyDissemination, self::WorkOrganization, self::ViolencePrevention => ActionLevel::Organizational,
            self::AwarenessCampaign, self::Training, self::SocialSupport, self::SpecificAssessment => ActionLevel::Group,
            self::ClinicalCare => ActionLevel::Individual,
            self::Other => null,
        };
    }
}
