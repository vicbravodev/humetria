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

    /** What the public form says under the name, so the person can tell which is theirs. */
    public function hint(): string
    {
        return match ($this) {
            self::UnfavorablePractice => 'Algo en su trabajo que lo vuelve injusto o muy pesado: cargas o jornadas excesivas, no saber qué le toca hacer, falta de reconocimiento, de formación o de comunicación, discriminación.',
            self::WorkplaceViolence => 'Hostigamiento, acoso o malos tratos contra usted o contra otra persona que trabaja en la empresa.',
            self::TraumaticEventNotice => 'Un aviso por escrito de que usted vivió o presenció un acontecimiento traumático severo en el trabajo. Para este aviso la norma exige su nombre: no puede ser anónimo.',
        };
    }
}
