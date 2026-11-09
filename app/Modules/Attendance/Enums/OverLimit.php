<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Enums;

/** A legal limit of arts. 66 and 68 that a week broke (criterios 2.3 and 2.4). */
enum OverLimit: string
{
    /** Ordinary plus overtime above 12 hours in a day (art. 68). */
    case DayOver12Hours = 'day_over_12_hours';

    /** More than 4 overtime hours in a day (art. 66). */
    case DailyOvertimeOver4 = 'daily_overtime_over_4';

    /** Overtime on more than 4 days of the week (art. 66). */
    case OvertimeDaysOver4 = 'overtime_days_over_4';

    /** More than 4 hours a week beyond the year's overtime cap (art. 68). */
    case TripleCapExceeded = 'triple_cap_exceeded';
}
