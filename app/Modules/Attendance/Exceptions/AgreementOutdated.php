<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Exceptions;

use RuntimeException;

/** The version a person read is no longer the company's current agreement: they read the new one first. */
final class AgreementOutdated extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The agreement changed.');
    }
}
