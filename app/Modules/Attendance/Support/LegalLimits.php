<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Enums\ShiftType;

/**
 * The limits of the 2026 reform of the working day, in minutes (docs/normas/jornada/criterios.md, 2.1 to 2.4).
 * A year before the reform takes 2026's limits; one after it, 2030's.
 */
final class LegalLimits
{
    /** Art. 66: at most 4 overtime hours in a day... */
    public const int DAILY_OVERTIME_MAX = 240;

    /** ...on at most 4 days of the week. */
    public const int OVERTIME_DAYS_MAX = 4;

    /** Art. 68: ordinary plus overtime never above 12 hours a day. */
    public const int DAY_TOTAL_MAX = 720;

    /** Art. 68: overtime beyond the cap of art. 66 (paid triple) at most 4 hours a week. */
    public const int TRIPLE_WEEKLY_MAX = 240;

    /** Transitorio segundo: weekly ordinary hours by year. */
    private const array WEEKLY_ORDINARY_HOURS = [2026 => 48, 2027 => 46, 2028 => 44, 2029 => 42, 2030 => 40];

    /** Transitorio cuarto: weekly overtime hours by year. */
    private const array WEEKLY_OVERTIME_HOURS = [2026 => 9, 2027 => 9, 2028 => 10, 2029 => 11, 2030 => 12];

    public static function weeklyOrdinaryMinutes(int $year): int
    {
        return self::WEEKLY_ORDINARY_HOURS[self::reformYear($year)] * 60;
    }

    public static function weeklyOvertimeCapMinutes(int $year): int
    {
        return self::WEEKLY_OVERTIME_HOURS[self::reformYear($year)] * 60;
    }

    public static function dailyOrdinaryMinutes(ShiftType $shift): int
    {
        return match ($shift) {
            ShiftType::Day => 480,
            ShiftType::Night => 420,
            ShiftType::Mixed => 450,
        };
    }

    /** @return 2026|2027|2028|2029|2030 */
    private static function reformYear(int $year): int
    {
        return max(2026, min(2030, $year));
    }
}
