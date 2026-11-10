<?php

declare(strict_types=1);

namespace App\Modules\Billing\Exceptions;

use RuntimeException;

/** A company has one current payer: to change it, close that link first (a transfer), never open a second. */
final class CompanyAlreadyHasPayer extends RuntimeException
{
    public static function paidBy(string $legalName): self
    {
        return new self("Esta empresa ya la paga {$legalName}");
    }
}
