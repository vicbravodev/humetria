<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Enums;

/** Where an announcement stands, from its dates and whether it was published: never stored. */
enum AnnouncementState: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Ended = 'ended';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Scheduled => 'Programado',
            self::Active => 'Activo',
            self::Ended => 'Terminado',
        };
    }
}
