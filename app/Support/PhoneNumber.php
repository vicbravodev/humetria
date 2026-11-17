<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Every phone of the app is stored in E.164 (`+525512345678`), read with libphonenumber's metadata: a number without
 * its country is Mexican, and one that is not valid for its country is rejected. As a rule, it passes what e164() reads.
 */
final class PhoneNumber implements ValidationRule
{
    /** The country of a number written without one; the form's selector starts on it too. */
    public const string DEFAULT_REGION = 'MX';

    /**
     * The number in E.164, or null when it is none. Since 2019 Mexico dials 10 digits for mobiles and landlines alike:
     * the 1 of mobiles after +52, and the 044/045 before them, are dropped, since people still type them.
     */
    public static function e164(?string $typed): ?string
    {
        if ($typed === null) {
            return null;
        }

        $util = PhoneNumberUtil::getInstance();

        try {
            $number = $util->parse(self::withoutMexicanLegacy($typed), self::DEFAULT_REGION);
        } catch (NumberParseException) {
            return null;
        }

        return $util->isValidNumber($number) ? $util->format($number, PhoneNumberFormat::E164) : null;
    }

    /** A stored number as people read it: a Mexican one as dialed inside Mexico, another one with its country. */
    public static function readable(?string $e164): ?string
    {
        if ($e164 === null) {
            return null;
        }

        $util = PhoneNumberUtil::getInstance();
        $number = $util->parse($e164);

        return $util->format($number, $util->getRegionCodeForNumber($number) === self::DEFAULT_REGION ? PhoneNumberFormat::NATIONAL : PhoneNumberFormat::INTERNATIONAL);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || self::e164($value) === null) {
            $fail('validation.phone')->translate();
        }
    }

    private static function withoutMexicanLegacy(string $typed): string
    {
        $digits = (string) preg_replace('/\D+/', '', $typed);
        // With a plus only +52 1 is Mexico's old mobile; without one, the number is Mexican.
        $legacy = str_starts_with(trim($typed), '+') ? '/\A521(\d{10})\z/' : '/\A(?:521|1|04[45])(\d{10})\z/';

        return preg_match($legacy, $digits, $match) === 1 ? '+52'.$match[1] : $typed;
    }
}
