<?php

declare(strict_types=1);

namespace App\Modules\Leave\Contracts;

use App\Modules\Leave\Data\LeaveCounts;
use Carbon\CarbonImmutable;

/** How many requests the current company got since a moment and how many of them were approved: counts only. */
interface LeaveActivity
{
    public function since(CarbonImmutable $since): LeaveCounts;
}
