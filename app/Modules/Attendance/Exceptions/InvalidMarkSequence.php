<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Exceptions;

use RuntimeException;

/**
 * A mark that does not follow the last one of the person: `day_already_open`, `day_not_open`, `already_on_break` or
 * `not_on_break`.
 */
final class InvalidMarkSequence extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("The mark breaks the sequence: {$reason}.");
    }
}
