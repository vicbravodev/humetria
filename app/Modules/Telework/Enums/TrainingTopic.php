<?php

declare(strict_types=1);

namespace App\Modules\Telework\Enums;

/** The topics the training has to cover «según aplique» (numeral 8.1, inciso a a c), in the norm's order. */
enum TrainingTopic: string
{
    case Hazards = 'hazards';
    case ErgonomicPostures = 'ergonomic_postures';
    case ErgonomicRepetitive = 'ergonomic_repetitive';
    case ErgonomicSitting = 'ergonomic_sitting';
    case Psychosocial = 'psychosocial';
    case PolicyUnderstanding = 'policy_understanding';
    case PolicyApplication = 'policy_application';
    case Ict = 'ict';

    public function label(): string
    {
        return match ($this) {
            self::Hazards => 'Reconocimiento de peligros por condiciones peligrosas e inseguras en el lugar de trabajo',
            self::ErgonomicPostures => 'Riesgo ergonómico por posturas forzosas',
            self::ErgonomicRepetitive => 'Riesgo ergonómico por movimientos repetitivos',
            self::ErgonomicSitting => 'Riesgo ergonómico por sedestación prolongada',
            self::Psychosocial => 'Factores de riesgo psicosocial en el teletrabajo',
            self::PolicyUnderstanding => 'Comprensión de la Política de Teletrabajo',
            self::PolicyApplication => 'Aplicación de la Política de Teletrabajo',
            self::Ict => 'Manejo de las tecnologías de la información y comunicación (TIC)',
        };
    }
}
