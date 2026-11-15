<?php

declare(strict_types=1);

namespace App\Modules\Seating\Data;

/**
 * One thing that waits for the Comisión in a center: `signatures` (assessments in draft, with whose they are, oldest first)
 * `minutes` (signed assessments not yet recorded in the minutes of the walk-through, counted only) or `unassessed`
 * (people who work standing and have no signed assessment, counted only).
 */
final readonly class CommitteeTask
{
    /**
     * @param  'signatures'|'minutes'|'unassessed'  $kind
     * @param  list<string>  $people
     */
    public function __construct(public string $workCenterId, public string $kind, public int $count, public array $people) {}
}
