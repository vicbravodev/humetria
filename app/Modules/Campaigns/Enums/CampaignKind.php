<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Enums;

/**
 * What a campaign applies. A NOM-035 evaluation covers a work center with Guía I and, by its headcount, Guía II or III;
 * a Guía I campaign applies only Guía I to chosen people after a severe traumatic event (criterio C-05).
 */
enum CampaignKind: string
{
    case Nom035Evaluation = 'nom035_evaluation';
    case Nom035Trauma = 'nom035_trauma';

    public function label(): string
    {
        return match ($this) {
            self::Nom035Evaluation => 'Evaluación NOM-035',
            self::Nom035Trauma => 'Guía I por acontecimiento',
        };
    }
}
