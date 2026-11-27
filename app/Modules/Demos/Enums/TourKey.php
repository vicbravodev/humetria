<?php

declare(strict_types=1);

namespace App\Modules\Demos\Enums;

/** A tour of a guided session: a module the demo can show. «base» (the center and its people) always goes first, unchosen. */
enum TourKey: string
{
    case Base = 'base';
    case Nom035 = 'nom035';
    case Attendance = 'attendance';
    case Engagement = 'engagement';
    case Complaints = 'complaints';
    case Leave = 'leave';
    case Humi = 'humi';

    /** How the guide and the room name the tour. */
    public function title(): string
    {
        return match ($this) {
            self::Base => 'El centro y su gente',
            self::Nom035 => 'NOM-035',
            self::Attendance => 'Asistencia',
            self::Engagement => 'Clima y pulso',
            self::Complaints => 'Denuncias',
            self::Leave => 'Vacaciones',
            self::Humi => 'Humi',
        };
    }
}
