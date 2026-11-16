<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Contracts;

use App\Modules\WorkerAccess\Data\WorkerIdentity;
use App\Modules\WorkerAccess\Data\WorkerSectionLink;

/**
 * What a tool offers the person in their start (`/me/{slug}`). Each module implements one and tags it `worker.sections` in
 * its provider; the start lists whatever answers a link, in a fixed order, and never learns what a tool is.
 */
interface WorkerSection
{
    /**
     * The link of the tool for the person of the session; null when the company does not have the tool or there is nothing
     * for them in it. The link carries no credential: the session is.
     */
    public function for(WorkerIdentity $person): ?WorkerSectionLink;
}
