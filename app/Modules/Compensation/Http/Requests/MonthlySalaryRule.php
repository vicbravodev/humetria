<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http\Requests;

use App\Modules\Compensation\Support\SalaryRules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** A monthly salary in pesos the way SalaryRules reads it: above zero and not absurdly high. */
final class MonthlySalaryRule implements ValidationRule
{
    /** A monthly salary above this is a typing mistake. In cents: $10,000,000. */
    private const int MAX_CENTS = 1_000_000_000;

    /** @param  Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cents = is_string($value) ? SalaryRules::pesos($value) : null;

        if ($cents === null || $cents <= 0 || $cents > self::MAX_CENTS) {
            $fail('El :attribute debe ser un monto en pesos como 15000 o 15000.50, mayor que cero y sin comas de miles.');
        }
    }
}
