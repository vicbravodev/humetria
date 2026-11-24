<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Exceptions;

use RuntimeException;

/** The push service answered 429: the job waits `$retryAfter` seconds (between 1 and 3600) and tries again. */
final class PushRateLimited extends RuntimeException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct('The push service asked to wait.');
    }
}
