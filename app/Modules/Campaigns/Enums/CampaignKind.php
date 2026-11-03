<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Enums;

/**
 * What a campaign applies. A NOM-035 evaluation covers a work center with Guía I and, by its headcount, Guía II or III;
 * a Guía I campaign applies only Guía I to chosen people after a severe traumatic event (criterio C-05). A seating survey
 * is the confidential survey of a work center's seats and rests, answered by everyone in it, with no sample.
 */
enum CampaignKind: string
{
    case Nom035Evaluation = 'nom035_evaluation';
    case Nom035Trauma = 'nom035_trauma';
    case SeatingSurvey = 'seating_survey';

    /**
     * The kinds of the NOM-035 pages, counts and digests: a seating survey is none of them.
     *
     * @return list<self>
     */
    public static function nom035(): array
    {
        return [self::Nom035Evaluation, self::Nom035Trauma];
    }

    public function label(): string
    {
        return match ($this) {
            self::Nom035Evaluation => 'Evaluación NOM-035',
            self::Nom035Trauma => 'Guía I por acontecimiento',
            self::SeatingSurvey => 'Encuesta de Ley Silla',
        };
    }
}
