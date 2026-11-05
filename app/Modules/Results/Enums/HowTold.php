<?php

declare(strict_types=1);

namespace App\Modules\Results\Enums;

/**
 * How the person learned they were channeled (board V4-N035-Canalizacion): in person and in private, with a short script, or
 * through a notice in their personal portal, read with their link.
 */
enum HowTold: string
{
    case InPerson = 'in_person';
    case Portal = 'portal';

    public function label(): string
    {
        return match ($this) {
            self::InPerson => 'En persona, en privado',
            self::Portal => 'Aviso en su portal',
        };
    }
}
