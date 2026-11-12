<?php

declare(strict_types=1);

namespace App\Modules\Billing\Exceptions;

use RuntimeException;

/** An extra charge hangs from a company's plan: without one there is nothing to add it to. */
final class CompanyHasNoPlan extends RuntimeException
{
    public static function forCharge(): self
    {
        return new self('Asigna un plan a la empresa antes de agregarle cargos.');
    }
}
