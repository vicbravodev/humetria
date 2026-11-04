<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use Illuminate\Support\Str;

/**
 * Reading a CSV as a person's spreadsheet saves it (Excel's byte order mark, Windows encoding, commas or semicolons, CRLF),
 * and writing a template. The rows keep the number they have in the spreadsheet, so a problem can be found there.
 */
final class CsvFile
{
    public const int MAX_ROWS = 2000;

    /**
     * The rows of the file keyed by their spreadsheet row (the header is row 1), each keyed by column, without the empty
     * ones; or what keeps the whole file from being read.
     *
     * @param  list<string>  $required  the columns the file must have
     * @return array{error: string|null, rows: array<int, array<string, string>>}
     */
    public static function read(string $content, array $required): array
    {
        $lines = preg_split('/\r\n|\r|\n/', self::utf8($content)) ?: [];
        $header = array_shift($lines) ?? '';
        $delimiter = substr_count($header, ';') > substr_count($header, ',') ? ';' : ',';
        $columns = array_map(fn (?string $name): string => Str::of((string) $name)->ascii()->lower()->trim()->replaceMatches('/\s+/', '_')->toString(), str_getcsv($header, $delimiter, '"', ''));

        foreach ($required as $column) {
            if (! in_array($column, $columns, true)) {
                return ['error' => "Falta la columna «{$column}». Usa la plantilla.", 'rows' => []];
            }
        }

        $rows = [];

        foreach ($lines as $index => $line) {
            $cells = array_map(fn (?string $cell): string => trim((string) $cell), str_getcsv($line, $delimiter, '"', ''));

            if (implode('', $cells) !== '') {
                $rows[$index + 2] = array_combine($columns, array_pad(array_slice($cells, 0, count($columns)), count($columns), ''));
            }
        }

        return match (true) {
            $rows === [] => ['error' => 'El archivo no tiene filas.', 'rows' => []],
            count($rows) > self::MAX_ROWS => ['error' => 'El archivo tiene más de '.number_format(self::MAX_ROWS).' filas. Divídelo en varios.', 'rows' => []],
            default => ['error' => null, 'rows' => $rows],
        };
    }

    /**
     * A template: its columns and one example row, with a byte order mark so Excel reads the accents.
     *
     * @param  list<string>  $columns
     * @param  list<string>  $example
     */
    public static function template(array $columns, array $example): string
    {
        return "\u{FEFF}".implode(',', $columns)."\n".implode(',', $example)."\n";
    }

    /** Text as UTF-8 without a byte order mark: a file that is not valid UTF-8 is read as Windows-1252, as Excel saves it. */
    private static function utf8(string $content): string
    {
        $text = str_starts_with($content, "\u{FEFF}") ? substr($content, 3) : $content;

        return mb_check_encoding($text, 'UTF-8') ? $text : mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
    }
}
