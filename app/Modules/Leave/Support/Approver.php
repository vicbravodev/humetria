<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Organization\Contracts\ReportingLines;
use App\Modules\Organization\Contracts\Roster;

/**
 * Whose it is to answer a person's request (criterio C-18): their direct manager and, when that one left, the first
 * active manager going up. With nobody active above, it is RH's.
 */
final readonly class Approver
{
    public function __construct(private ReportingLines $lines, private Roster $roster) {}

    public function for(string $employeeId): ?string
    {
        $chain = $this->lines->chainOf($employeeId);
        $people = $this->roster->findMany($chain);

        foreach ($chain as $managerId) {
            if ($people[$managerId]->active ?? false) {
                return $managerId;
            }
        }

        return null;
    }
}
