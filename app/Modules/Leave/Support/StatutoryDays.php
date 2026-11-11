<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * The working days of vacation the law gives for each completed year of service (LFT art. 76; criterios C-01 and C-02).
 * The floor no policy may go under.
 */
final class StatutoryDays
{
    /** The day the reform of DOF 27-12-2022 took effect: an anniversary before it keeps the earlier table (C-02). */
    public const string REFORM = '2023-01-01';

    public static function for(int $years, CarbonImmutable $anniversary): int
    {
        return $anniversary->toDateString() < self::REFORM ? self::previous($years) : self::current($years);
    }

    /**
     * 12 days the first year, 2 more each year up to 20; from the sixth year, 2 more for every five.
     */
    public static function current(int $years): int
    {
        self::guard($years);

        return $years <= 5 ? 10 + 2 * $years : 20 + 2 * intdiv($years - 1, 5);
    }

    /**
     * 6 days the first year, 2 more each year up to 12; after the fourth year, 2 more for every five.
     */
    private static function previous(int $years): int
    {
        self::guard($years);

        return $years <= 4 ? 4 + 2 * $years : 12 + 2 * intdiv($years, 5);
    }

    private static function guard(int $years): void
    {
        if ($years < 1) {
            throw new InvalidArgumentException('Vacation days start with the first completed year of service.');
        }
    }
}
