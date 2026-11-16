<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Exceptions;

use RuntimeException;

/**
 * The person already has as many keys in use as config/worker_access.php allows (3): RH revokes one before another is added.
 */
final class TooManyKeys extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The person already has the most keys allowed.');
    }
}
