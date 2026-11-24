<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Exceptions;

use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * A schedule cannot start on a day inside, or before, a week the person already confirmed or disputed: it would change what
 * they signed and the report of those weeks. `from` is the first Monday it can start on; the controller says it in a validation error.
 */
final class ScheduleStartLocked extends RuntimeException
{
    public function __construct(public readonly string $employeeId, public readonly CarbonImmutable $from)
    {
        parent::__construct('The schedule cannot start in a week the person already answered.');
    }
}
