<?php

declare(strict_types=1);

namespace App\Modules\Leave\Exceptions;

use RuntimeException;

/**
 * The days of someone who works here are not settled: they are theirs again. Nothing was changed.
 */
final class PersonWorksHere extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Esta persona está activa.');
    }
}
