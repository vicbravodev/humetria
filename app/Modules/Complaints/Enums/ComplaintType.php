<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Enums;

/** What a person reports through the channel (NOM-035 8.1 b and 6.5). */
enum ComplaintType: string
{
    case UnfavorablePractice = 'unfavorable_practice';
    case WorkplaceViolence = 'workplace_violence';
    case TraumaticEventNotice = 'traumatic_event_notice';

    public function label(): string
    {
        return match ($this) {
            self::UnfavorablePractice => 'Queja por una práctica opuesta a un entorno favorable',
            self::WorkplaceViolence => 'Denuncia de violencia laboral',
            self::TraumaticEventNotice => 'Aviso de un acontecimiento traumático severo',
        };
    }
}
