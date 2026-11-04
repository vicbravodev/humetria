<?php

declare(strict_types=1);

namespace App\Modules\Organization\Data;

use App\Modules\Organization\Enums\StaffGroup;
use Carbon\CarbonImmutable;

/**
 * How many active people share a category and level, area, staff group, state and month of hire. Nobody in it can be
 * singled out: it is a count.
 */
final readonly class HeadcountCell
{
    /**
     * @param  CarbonImmutable  $hireMonth  the first day of the month of hire, at midnight UTC
     * @param  int<1, max>  $count
     */
    public function __construct(
        public string $jobGradeId,
        public ?string $areaId,
        public StaffGroup $group,
        public ?string $state,
        public CarbonImmutable $hireMonth,
        public int $count,
    ) {}
}
