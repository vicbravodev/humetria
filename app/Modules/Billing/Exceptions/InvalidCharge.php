<?php

declare(strict_types=1);

namespace App\Modules\Billing\Exceptions;

use RuntimeException;

/** A change to an extra charge the rules of billing do not allow; it says which field of the form is at fault. */
final class InvalidCharge extends RuntimeException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}
