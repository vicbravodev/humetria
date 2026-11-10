<?php

declare(strict_types=1);

namespace App\Modules\Billing\Exceptions;

use RuntimeException;

/** A statement the rules of billing do not allow to be made, issued or adjusted; it says which field of the form is at fault. */
final class InvalidStatement extends RuntimeException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}
