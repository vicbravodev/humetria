<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The file of opening balances (criterio C-21): one row per person and period, with the days that were pending. Read as a
 * person's spreadsheet saves it (Excel's byte order mark, Windows encoding, commas or semicolons, CRLF).
 */
final class OpeningCsv
{
    /** The employee number, the calendar year of the anniversary (2025) and the days still pending of that period. */
    public const array COLUMNS = ['numero_de_empleado', 'aniversario', 'dias_pendientes'];

    /**
     * The rows of the file without the empty ones, each with its columns and the line it is on.
     *
     * @return list<array<string, string>>
     *
     * @throws ValidationException when the file as a whole cannot be taken
     */
    public static function read(string $path): array
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw ValidationException::withMessages(['file' => 'No se pudo leer el archivo.']);
        }

        $text = str_starts_with($content, "\u{FEFF}") ? substr($content, 3) : $content;
        $text = mb_check_encoding($text, 'UTF-8') ? $text : mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $header = array_shift($lines) ?? '';
        $delimiter = substr_count($header, ';') > substr_count($header, ',') ? ';' : ',';
        $columns = array_map(fn (?string $name): string => Str::of((string) $name)->ascii()->lower()->trim()->replaceMatches('/\s+/', '_')->toString(), str_getcsv($header, $delimiter, '"', ''));

        foreach (self::COLUMNS as $required) {
            if (! in_array($required, $columns, true)) {
                throw ValidationException::withMessages(['file' => "Falta la columna «{$required}». Usa la plantilla."]);
            }
        }

        $rows = [];

        foreach ($lines as $index => $line) {
            $cells = array_map(fn (?string $cell): string => trim((string) $cell), str_getcsv($line, $delimiter, '"', ''));

            if (implode('', $cells) !== '') {
                $row = ['line' => (string) ($index + 2)];

                foreach (self::COLUMNS as $column) {
                    $row[$column] = $cells[array_search($column, $columns, true)] ?? '';
                }

                $rows[] = $row;
            }
        }

        $max = config()->integer('leave.import_max_rows');

        return match (true) {
            $rows === [] => throw ValidationException::withMessages(['file' => 'El archivo no tiene filas.']),
            count($rows) > $max => throw ValidationException::withMessages(['file' => 'El archivo tiene más de '.number_format($max).' filas. Divídelo en varios.']),
            default => $rows,
        };
    }
}
