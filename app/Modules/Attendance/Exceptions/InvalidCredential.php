<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Exceptions;

use RuntimeException;

/**
 * A key that did not verify while linking a phone. One message for every cause (a bad signature, a used challenge, a key
 * already linked...), so the answer never tells which check failed.
 */
final class InvalidCredential extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The credential could not be verified.');
    }
}
