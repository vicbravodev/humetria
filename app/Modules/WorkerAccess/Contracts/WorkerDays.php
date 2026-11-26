<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Contracts;

use App\Modules\WorkerAccess\Data\WorkerDay;
use App\Modules\WorkerAccess\Data\WorkerIdentity;

/**
 * The day of the person in their start (`/me/{slug}`): the module that keeps the marks implements it, so the start never
 * learns what a mark is.
 */
interface WorkerDays
{
    /** The day of the person of the session; null when the company does not keep one. */
    public function of(WorkerIdentity $person): ?WorkerDay;
}
