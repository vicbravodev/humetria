<?php

declare(strict_types=1);

namespace App\Modules\Organization\Support;

use Illuminate\Support\Str;

/**
 * The employees CSV: its columns, its template, and reading a file as a person's spreadsheet saves it (Excel's
 * byte order mark, Windows encoding, commas or semicolons, CRLF).
 */
final class EmployeeCsv
{
    public const int MAX_ROWS = 2000;

    /** The template's columns; correo, celular and numero_de_empleado may be left out of a file. */
    public const array COLUMNS = ['nombre', 'correo', 'celular', 'genero', 'centro_de_trabajo', 'area', 'puesto', 'numero_de_empleado'];

    private const array REQUIRED = ['nombre', 'genero', 'centro_de_trabajo', 'area', 'puesto'];

    public static function template(string $workCenter): string
    {
        $example = ['Ana López', 'ana@empresa.mx', '55 1234 5678', 'Mujer', $workCenter, 'Producción', 'Operadora', 'A-001'];

        return "\u{FEFF}".implode(',', self::COLUMNS)."\n".self::line($example)."\n";
    }

    /**
     * The file's rows, each keyed by column, without the empty ones; or what keeps the whole file from being read.
     *
     * @return array{error: string|null, rows: list<array<string, string>>}
     */
    public static function read(string $content): array
    {
        $text = self::utf8($content);
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $header = array_shift($lines) ?? '';
        $delimiter = substr_count($header, ';') > substr_count($header, ',') ? ';' : ',';
        $columns = array_map(fn (?string $name): string => Str::of((string) $name)->ascii()->lower()->trim()->replaceMatches('/\s+/', '_')->toString(), str_getcsv($header, $delimiter, '"', ''));

        foreach (self::REQUIRED as $required) {
            if (! in_array($required, $columns, true)) {
                return ['error' => "Falta la columna «{$required}». Usa la plantilla.", 'rows' => []];
            }
        }

        $rows = [];

        foreach ($lines as $line) {
            $cells = array_map(fn (?string $cell): string => trim((string) $cell), str_getcsv($line, $delimiter, '"', ''));

            if (implode('', $cells) !== '') {
                $rows[] = array_combine($columns, array_pad(array_slice($cells, 0, count($columns)), count($columns), ''));
            }
        }

        return match (true) {
            $rows === [] => ['error' => 'El archivo no tiene empleados.', 'rows' => []],
            count($rows) > self::MAX_ROWS => ['error' => 'El archivo tiene más de 2,000 empleados. Divídelo en varios.', 'rows' => []],
            default => ['error' => null, 'rows' => $rows],
        };
    }

    /** Text as UTF-8 without a byte order mark: a file that is not valid UTF-8 is read as Windows-1252, as Excel saves it. */
    private static function utf8(string $content): string
    {
        $text = str_starts_with($content, "\u{FEFF}") ? substr($content, 3) : $content;

        return mb_check_encoding($text, 'UTF-8') ? $text : mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
    }

    /**
     * @param  list<string>  $cells
     */
    private static function line(array $cells): string
    {
        return implode(',', array_map(fn (string $cell): string => str_contains($cell, ',') ? '"'.str_replace('"', '""', $cell).'"' : $cell, $cells));
    }
}
