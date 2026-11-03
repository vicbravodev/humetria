<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support;

/**
 * Masks what identifies a person in the text of a message before it leaves for the provider and before it is kept:
 * emails, CURP, RFC, NSS (social security number) and phone numbers. Order matters: a CURP starts like an RFC and an NSS
 * has the digits of a phone.
 */
final class PersonalDataRedactor
{
    private const array PATTERNS = [
        '[correo]' => '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',
        '[CURP]' => '/\b[A-Z]{4}\d{6}[HM][A-Z]{2}[B-DF-HJ-NP-TV-Z]{3}[A-Z0-9]\d\b/i',
        '[RFC]' => '/\b[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}\b/iu',
        '[NSS]' => '/(?<!\d)\d{2}[- ]?\d{2}[- ]?\d{2}[- ]?\d{4}[- ]?\d(?!\d)/',
        '[teléfono]' => '/(?<![\d-])(?:\+?52[ .-]?)?\(?\d{2,3}\)?[ .-]?\d{3,4}[ .-]?\d{4}(?!\d)/',
    ];

    public function redact(string $text): string
    {
        foreach (self::PATTERNS as $marker => $pattern) {
            $text = (string) preg_replace($pattern, $marker, $text);
        }

        return $text;
    }
}
