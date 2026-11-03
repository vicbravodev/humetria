<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Exceptions;

use RuntimeException;

/** A tool was asked to work for a company other than the one the request is in: nothing is read. */
final class ScopeMismatch extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The scope of the assistant is not the company of the request.');
    }
}
