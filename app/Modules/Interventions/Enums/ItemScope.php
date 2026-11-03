<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Enums;

/**
 * What a row of the Programa attends: the final score, a category or a domain of the guide (criterio C-30).
 */
enum ItemScope: string
{
    case Final = 'final';
    case Category = 'category';
    case Domain = 'domain';

    public function label(): string
    {
        return match ($this) {
            self::Final => 'Calificación final',
            self::Category => 'Categoría',
            self::Domain => 'Dominio',
        };
    }
}
