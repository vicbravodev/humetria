<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Contracts;

use App\Modules\Complaints\Data\ComplaintCounts;
use Carbon\CarbonImmutable;

/**
 * How many complaints the current company received since a moment and how many of them were acknowledged: counts only.
 * A complaint keeps only its day, never its hour, so «since» starts at the day of `$since` in the display time zone.
 */
interface ComplaintActivity
{
    public function since(CarbonImmutable $since): ComplaintCounts;
}
