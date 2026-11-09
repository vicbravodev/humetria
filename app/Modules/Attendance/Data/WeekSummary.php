<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Data;

use App\Modules\Attendance\Enums\OverLimit;

/** A week's minutes split by how the law pays them (criterio 2.9), with the limits it broke. */
final readonly class WeekSummary
{
    /**
     * @param  list<OverLimit>  $flags  each limit broken, once, in the order of the enum
     * @param  array<string, int>  $overtimeByDay  overtime minutes by date (Y-m-d), only the days that have any
     */
    public function __construct(
        public int $ordinaryMinutes,
        public int $overtimeMinutes,
        public int $doubleMinutes,
        public int $tripleMinutes,
        public int $restDayMinutes,
        public array $flags,
        public array $overtimeByDay,
    ) {}
}
