<?php

declare(strict_types=1);

namespace App\Modules\Seating\Contracts;

use App\Modules\Seating\Data\CommitteeTask;

/**
 * What waits for the Comisión in Ley Silla (rediseño v2, board V2-Comision), for the queue that gathers its work of every
 * standard: the assessments to sign, with whose they are, and the signed ones still out of the minutes of the walk-through.
 * Names and counts only, never a score nor an answer.
 */
interface CommitteeWork
{
    /**
     * @param  list<string>  $workCenterIds
     * @return list<CommitteeTask> in the order of the centers given, what to sign before what to record
     */
    public function waiting(array $workCenterIds): array;
}
