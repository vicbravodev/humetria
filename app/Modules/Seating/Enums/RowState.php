<?php

declare(strict_types=1);

namespace App\Modules\Seating\Enums;

/**
 * Where a requirement of a work center stands on the board. None of them is an «apto»: they are counts of what was
 * registered. A warning is a requirement that is met, with something to review.
 */
enum RowState: string
{
    case Met = 'met';
    case Warning = 'warning';
    case Missing = 'missing';

    public function label(): string
    {
        return match ($this) {
            self::Met => 'Cumple',
            self::Warning => 'Con aviso',
            self::Missing => 'Falta',
        };
    }
}
