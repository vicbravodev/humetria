<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Exceptions;

use RuntimeException;

/** A change of a company's modules by hand that the rules do not allow; it says which field of the form is at fault. */
final class InvalidModuleChange extends RuntimeException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}
