<?php

declare(strict_types=1);

namespace App\Modules\Telework\Enums;

/** How the Comisión reached a decision: the person's checklist, an in-person visit or a remote check. */
enum ReviewMethod: string
{
    case Checklist = 'checklist';
    case Visit = 'visit';
    case RemoteCheck = 'remote_check';

    public function label(): string
    {
        return match ($this) {
            self::Checklist => 'Lista de verificación',
            self::Visit => 'Visita',
            self::RemoteCheck => 'Comprobación a distancia',
        };
    }
}
