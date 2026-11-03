<?php

declare(strict_types=1);

namespace App\Modules\Seating\Enums;

/**
 * The types of seat the Disposiciones suggest (Quinta E): a high stool, a high chair, an ergonomic chair or a footrest.
 */
enum SeatKind: string
{
    case HighStool = 'high_stool';
    case HighChair = 'high_chair';
    case ErgonomicChair = 'ergonomic_chair';
    case Footrest = 'footrest';

    public function label(): string
    {
        return match ($this) {
            self::HighStool => 'Banco alto o tipo perchero',
            self::HighChair => 'Silla alta con respaldo medio',
            self::ErgonomicChair => 'Silla ergonómica ajustable',
            self::Footrest => 'Reposapiés',
        };
    }
}
