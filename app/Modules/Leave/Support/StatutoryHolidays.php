<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use Carbon\CarbonImmutable;

/**
 * The mandatory rest days of LFT art. 74 that a calendar can tell (criterio C-04). The election day (fraction IX) depends
 * on federal and local electoral laws: the company adds it as a holiday of its own.
 */
final class StatutoryHolidays
{
    /** The first year 1 October was the day the federal executive changes (fraction VII, reformed DOF 30-09-2024). */
    private const int FIRST_TRANSFER = 2024;

    /**
     * @return list<string> Y-m-d, in order
     */
    public static function for(int $year): array
    {
        $transfer = $year >= self::FIRST_TRANSFER && ($year - self::FIRST_TRANSFER) % 6 === 0;

        return [
            "{$year}-01-01",
            self::monday($year, 2, 1),
            self::monday($year, 3, 3),
            "{$year}-05-01",
            "{$year}-09-16",
            ...($transfer ? ["{$year}-10-01"] : []),
            self::monday($year, 11, 3),
            "{$year}-12-25",
        ];
    }

    private static function monday(int $year, int $month, int $nth): string
    {
        $first = CarbonImmutable::parse(sprintf('%04d-%02d-01', $year, $month));

        return $first->addDays((8 - $first->dayOfWeekIso) % 7 + 7 * ($nth - 1))->toDateString();
    }
}
