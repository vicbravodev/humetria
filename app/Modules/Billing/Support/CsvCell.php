<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

/** A cell of an export that a spreadsheet will not run as a formula, whatever a person typed in a name or a note. */
final class CsvCell
{
    public static function safe(string $text): string
    {
        return $text !== '' && str_contains("=+-@\t\r", $text[0]) ? "'{$text}" : $text;
    }
}
