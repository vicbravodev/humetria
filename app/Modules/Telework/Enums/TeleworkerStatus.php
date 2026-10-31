<?php

declare(strict_types=1);

namespace App\Modules\Telework\Enums;

/**
 * Aspirant: registered, still being validated. Active: in the roster. Ended: the period closed (a new one may open later).
 */
enum TeleworkerStatus: string
{
    case Aspirant = 'aspirant';
    case Active = 'active';
    case Ended = 'ended';
}
