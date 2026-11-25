<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Enums;

/** What Recursos Humanos decided about a mark made without a connection (criterio 5.4). */
enum ReviewDecision: string
{
    case Verified = 'verified';
    case Incorrect = 'incorrect';
}
