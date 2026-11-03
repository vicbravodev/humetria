<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

/**
 * The seats of a position against the people who use it at once. `short` is an aid, not a standard: the law fixes no number
 * of seats per person (criterio C-01).
 */
final readonly class SeatCountResult
{
    public function __construct(public int $seats, public int $maxPeopleAtOnce, public bool $short) {}
}
