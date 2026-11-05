<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http\Requests;

use App\Modules\Compensation\Support\PremiumRate;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** The work risk premium the way PremiumRate reads it: a percent with up to five decimals. */
final class PremiumRule implements ValidationRule
{
    /** @param  Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || PremiumRate::parse($value) === null) {
            $fail('La :attribute debe ser un porcentaje como 7.58875, con hasta cinco decimales.');
        }
    }
}
