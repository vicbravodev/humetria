<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

/**
 * A random code in Crockford's base 32 (no I, L, O or U): what a person can read aloud or copy from a poster without
 * mistaking one symbol for another. Drawn with the system's secure generator.
 */
final class RandomCode
{
    public const string ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public static function make(int $length): string
    {
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= self::ALPHABET[random_int(0, 31)];
        }

        return $code;
    }
}
