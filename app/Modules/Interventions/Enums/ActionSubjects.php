<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Enums;

/**
 * Who an action is for (num. 8.4 a; criterio C-33): the whole work center or areas shown in Resultados. Never people.
 */
enum ActionSubjects: string
{
    case Center = 'center';
    case Areas = 'areas';

    public function label(): string
    {
        return match ($this) {
            self::Center => 'Todo el centro de trabajo',
            self::Areas => 'Áreas',
        };
    }
}
