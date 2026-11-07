<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Enums;

/** Where a person's week stands (criterio 5.2). Silence is not conformity: a week nobody answered stays pending. */
enum WeekStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Disputed = 'disputed';
}
