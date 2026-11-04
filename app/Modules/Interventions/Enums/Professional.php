<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Enums;

/**
 * Who gives a clinical or therapeutic intervention of the third level: "invariablemente por un médico, psicólogo o
 * psiquiatra según corresponda" (num. 8.5, last paragraph; criterio C-32).
 */
enum Professional: string
{
    case Physician = 'physician';
    case Psychologist = 'psychologist';
    case Psychiatrist = 'psychiatrist';

    public function label(): string
    {
        return match ($this) {
            self::Physician => 'Médico',
            self::Psychologist => 'Psicólogo',
            self::Psychiatrist => 'Psiquiatra',
        };
    }
}
