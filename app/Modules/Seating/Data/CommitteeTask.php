<?php

declare(strict_types=1);

namespace App\Modules\Seating\Data;

/**
 * One thing that waits for the Comisión in a center: `signatures` (assessments in draft, with whose they are, oldest first)
 * or `minutes` (signed assessments not yet recorded in the minutes of the walk-through, counted only).
 */
final readonly class CommitteeTask
{
    /**
     * @param  'signatures'|'minutes'  $kind
     * @param  list<string>  $people
     */
    public function __construct(public string $workCenterId, public string $kind, public int $count, public array $people) {}
}
