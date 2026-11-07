<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Exceptions;

use RuntimeException;

/**
 * A person's answer about a week that cannot be taken: `week_not_over` (only a week whose Sunday ended is answered),
 * `week_disputed` (RH resolves a dispute; the person does not confirm over it) or `already_disputed`.
 */
final class WeekRejected extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("The answer about the week was rejected: {$reason}.");
    }
}
