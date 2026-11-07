<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Exceptions;

use RuntimeException;

/** The work center (or the zone) is archived or not the company's; the controller answers 404. */
final class WorkCenterNotAvailable extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The work center is not available.');
    }
}
