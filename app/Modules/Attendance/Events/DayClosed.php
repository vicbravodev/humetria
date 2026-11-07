<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Events;

/**
 * A person's local day ended and was closed (its date, Y-m-d). `missingEnd`: it was left with no end mark, which is
 * flagged and never closed by itself. Ids only: never a time or a position.
 */
final readonly class DayClosed
{
    public function __construct(public string $employeeId, public string $date, public bool $missingEnd) {}
}
