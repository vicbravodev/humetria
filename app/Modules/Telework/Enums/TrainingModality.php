<?php

declare(strict_types=1);

namespace App\Modules\Telework\Enums;

/** How a training was given: «remota, presencial o mixta» (numeral 8.1, nota). */
enum TrainingModality: string
{
    case Remote = 'remote';
    case InPerson = 'in_person';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Remote => 'Remota',
            self::InPerson => 'Presencial',
            self::Mixed => 'Mixta',
        };
    }
}
