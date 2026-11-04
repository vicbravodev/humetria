<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http\Requests;

use App\Modules\Compensation\Support\SalaryRules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** A daily salary the way SalaryRules reads it: pesos and cents, above zero and not absurdly high. */
final class SalaryRule implements ValidationRule
{
    /** @param  Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $problem = SalaryRules::salaryProblem(is_string($value) ? SalaryRules::pesos($value) : null);

        if ($problem !== null) {
            $fail($problem);
        }
    }
}
