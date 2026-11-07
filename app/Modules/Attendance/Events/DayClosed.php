<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Events;

/**
 * A person's local day ended and was closed (its date, Y-m-d). `missingEnd`: it was left with no end mark, which is
 * flagged and never closed by itself. Ids only: never a time or a position. Closing keeps no state, so a manual re-run of
 * the nightly close fires it again for the same day: listeners must be idempotent.
 */
final readonly class DayClosed
{
    public function __construct(public string $employeeId, public string $date, public bool $missingEnd) {}
}
