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

    /**
     * A headcount that is assumed, not read from the roster (an average payroll): unionized unless said trusted, in no area.
     *
     * @param  int<1, max>  $count
     */
    public static function assumed(string $jobGradeId, ?string $state, CarbonImmutable $hireMonth, int $count, bool $trusted = false): self
    {
        return new self($jobGradeId, null, $trusted ? StaffGroup::Trusted : StaffGroup::Unionized, $state, $hireMonth, $count);
    }
}
