<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support;

/**
 * Text that the company typed (the name of a center or an area) and that Humi reads as data. It can carry orders meant
 * for the model, so it goes out on one line, without markup and short: it cannot close a tag or open a paragraph of
 * its own. It is still words; the system prompt tells the model never to obey them.
 */
final class UntrustedText
{
    private const int MAX_LENGTH = 120;

    public static function line(string $text): string
    {
        $plain = str_replace(['<', '>'], '', $text);
        $oneLine = trim((string) preg_replace('/\s+/u', ' ', $plain));

        return mb_strimwidth($oneLine, 0, self::MAX_LENGTH, '…');
    }
}
