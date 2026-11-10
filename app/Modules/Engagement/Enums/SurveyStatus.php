<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Enums;

enum SurveyStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Active => 'En curso',
            self::Closed => 'Cerrada',
        };
    }
}
