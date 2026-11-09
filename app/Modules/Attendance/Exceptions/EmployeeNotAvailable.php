<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Exceptions;

use RuntimeException;

/** The person left the company (deactivated) or is not the company's; the controller answers 404. */
final class EmployeeNotAvailable extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The employee is not available.');
    }
}
