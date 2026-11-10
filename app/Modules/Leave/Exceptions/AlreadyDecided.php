<?php

declare(strict_types=1);

namespace App\Modules\Leave\Exceptions;

use RuntimeException;

/**
 * Someone answered or cancelled the request first: nothing was changed.
 */
final class AlreadyDecided extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Esta solicitud ya fue respondida.');
    }
}
