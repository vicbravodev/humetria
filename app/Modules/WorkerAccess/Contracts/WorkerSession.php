<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Contracts;

use App\Modules\WorkerAccess\Data\WorkerIdentity;

/**
 * Who the person of this request is: the modules of the person's pages (`/me/{slug}/...`) ask here, never for a token.
 */
interface WorkerSession
{
    /**
     * The person whose session is open for the company of the request, or null for a visitor. A session that no longer
     * holds (another company, too long unused or open, revoked, a person who left, a blocked company) is forgotten.
     */
    public function current(): ?WorkerIdentity;
}
