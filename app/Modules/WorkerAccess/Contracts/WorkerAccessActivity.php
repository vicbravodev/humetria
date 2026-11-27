<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Contracts;

use App\Modules\WorkerAccess\Data\AccessActivity;
use Carbon\CarbonImmutable;

/** How many people of the current company were sent their access and how many keys were created since a moment: counts only. */
interface WorkerAccessActivity
{
    public function since(CarbonImmutable $since): AccessActivity;
}
