<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Data;

use Carbon\CarbonImmutable;

/** What a person worked one day against what their schedule set for it, in minutes. */
final readonly class DayHours
{
    public function __construct(
        public CarbonImmutable $date,
        public int $workedMinutes,
        public int $scheduledMinutes,
        public bool $restDay,
    ) {}
}
