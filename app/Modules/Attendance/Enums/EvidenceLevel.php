<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Enums;

/** How much a mark proves by the way it was made (criterio 5.3). */
enum EvidenceLevel: string
{
    case Strong = 'strong';
    case Medium = 'medium';
    case Basic = 'basic';
    case Corrected = 'corrected';
}
