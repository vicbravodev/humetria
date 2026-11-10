<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

/**
 * The twelve continuous days of LFT art. 78 (criterio C-06). How to spread the period is the person's choice: this only
 * tells whether to remind them, it never stops a request.
 */
final class ContinuousRun
{
    public const int MINIMUM = 12;

    /**
     * Whether none of the approved or enjoyed requests of a period reaches the twelve working days in a row.
     *
     * @param  list<int>  $workingDaysOfRequests
     */
    public static function missing(array $workingDaysOfRequests): bool
    {
        return max([0, ...$workingDaysOfRequests]) < self::MINIMUM;
    }
}
