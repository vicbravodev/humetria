<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Exceptions;

use RuntimeException;

/** The push service failed (5xx) or did not answer: the queue tries again with its backoff. */
final class PushUnavailable extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The push service is unavailable.');
    }
}
