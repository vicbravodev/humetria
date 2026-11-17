<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use App\Modules\Complaints\Contracts\ComplaintChannels;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Contracts\WorkerSection;
use App\Modules\WorkerAccess\Data\WorkerIdentity;
use App\Modules\WorkerAccess\Data\WorkerSectionLink;

/**
 * «Quejas y denuncias» in the person's start: the way to the public channel of the company while it takes complaints.
 * The link is the channel's own, the same for everybody: nothing of the person or of their pages travels in it, so the
 * channel keeps no way to know who came from where (criterio C-46).
 */
final readonly class ComplaintsWorkerSection implements WorkerSection
{
    public function __construct(private Companies $companies, private ComplaintChannels $channels) {}

    public function for(WorkerIdentity $person): ?WorkerSectionLink
    {
        $url = $this->channels->current()->url;

        // The company is read only when the channel takes complaints.
        if ($url === null || $this->companies->current()->stopped('complaints')) {
            return null;
        }

        return new WorkerSectionLink('complaints', 'Quejas y denuncias', 'Con confidencialidad', $url);
    }
}
