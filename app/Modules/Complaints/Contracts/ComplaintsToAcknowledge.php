<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Contracts;

use App\Modules\Complaints\Data\ComplaintToAcknowledge;

/**
 * The complaints assigned to a person that still wait for their acknowledgment, as the frame and the panel count and list
 * them: the kind and the deadline, never what the complaint says, who presented it or whom it is about.
 */
interface ComplaintsToAcknowledge
{
    public function count(int $userId): int;

    /**
     * The soonest to fall due first, up to `$limit`.
     *
     * @return list<ComplaintToAcknowledge>
     */
    public function soonest(int $userId, int $limit): array;
}
