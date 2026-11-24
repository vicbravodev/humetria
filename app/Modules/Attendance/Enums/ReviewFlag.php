<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Enums;

/**
 * The closed set of flags a mark made without a connection can carry for Recursos Humanos (criterio 3.3.1). Flagged marks
 * come first in the list; every mark, flagged or not, stays «por verificar» until Recursos Humanos reviews it.
 */
enum ReviewFlag: string
{
    /** The proven interval is wider than the cut (240 min). */
    case WideInterval = 'wide_interval';
    /** With a QR, the declared time is more than 30 min after its window: it is treated as having none. */
    case OldWindow = 'old_window';
    case NoPosition = 'no_position';
    case OutsideZone = 'outside_zone';
    /** The counted time differs from the declared one. */
    case DeclaredAdjusted = 'declared_adjusted';
}
