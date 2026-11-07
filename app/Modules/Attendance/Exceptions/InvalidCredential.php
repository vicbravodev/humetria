<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Exceptions;

use RuntimeException;

/**
 * A WebAuthn answer that did not verify. One message for every cause (a bad signature, a used challenge, a revoked
 * device...), so the answer never tells which check failed.
 */
final class InvalidCredential extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The credential could not be verified.');
    }
}
