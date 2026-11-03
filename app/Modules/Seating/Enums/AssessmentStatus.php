<?php

declare(strict_types=1);

namespace App\Modules\Seating\Enums;

/**
 * The two states of an individual assessment: a draft that can still change and a signed one that never does (criterio C-17).
 */
enum AssessmentStatus: string
{
    case Draft = 'draft';
    case Signed = 'signed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Signed => 'Firmada',
        };
    }
}
