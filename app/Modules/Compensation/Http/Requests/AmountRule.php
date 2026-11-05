<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http\Requests;

use App\Modules\Compensation\Support\BenefitValue;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** A number the way BenefitValue reads it ("500", "12.5"); a field that may be left empty adds `nullable`. */
final class AmountRule implements ValidationRule
{
    /** @param  Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || BenefitValue::parse($value) === null) {
            $fail('El campo :attribute debe ser un número como 500 o 12.5, sin signos ni comas de miles.');
        }
    }
}
