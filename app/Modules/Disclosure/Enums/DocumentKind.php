<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Enums;

/**
 * What a text of the company is for: the policy (5.1, 5.7 a) or one of the blocks of 5.7 that the company writes itself
 * (b, c, d and f).
 */
enum DocumentKind: string
{
    case Policy = 'policy';
    case ViolenceMeasures = 'violence_measures';
    case PreventionMeasures = 'prevention_measures';
    case ComplaintMechanism = 'complaint_mechanism';
    case HealthEffects = 'health_effects';

    public function label(): string
    {
        return match ($this) {
            self::Policy => 'Política de prevención',
            self::ViolenceMeasures => 'Medidas contra la violencia laboral',
            self::PreventionMeasures => 'Medidas y acciones de prevención y control',
            self::ComplaintMechanism => 'Mecanismos para presentar quejas',
            self::HealthEffects => 'Posibles alteraciones a la salud',
        };
    }
}
