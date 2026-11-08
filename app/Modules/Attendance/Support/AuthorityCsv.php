<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Data\AuthorityRow;

/**
 * The report for the authority as a spreadsheet opens it: UTF-8 with a BOM (Excel reads the accents), commas, LF, a header
 * of stable names in Spanish and one row per person-day. Minutes as integers and again as h:mm for people. Free text
 * (names, job titles, reasons) cannot run as a formula: a cell that starts with =, +, -, @, a tab or a carriage return
 * gets a single quote first (OWASP, CSV injection).
 */
final class AuthorityCsv
{
    public const array HEADER = [
        'Persona', 'Puesto', 'Fecha', 'Inicio', 'Fin', 'Fecha de fin', 'Pausas (min)',
        'Trabajado (min)', 'Trabajado (h:mm)', 'Ordinarias (min)', 'Ordinarias (h:mm)',
        'Extra doble (min)', 'Extra doble (h:mm)', 'Extra triple (min)', 'Extra triple (h:mm)',
        'Día de descanso (min)', 'Día de descanso (h:mm)',
        'Evidencia', 'Correcciones', 'Detalle de correcciones', 'Conformidad', 'Huella de la semana confirmada', 'Avisos',
    ];

    /**
     * Writes the whole file to `$out`, row by row as they come.
     *
     * @param  iterable<AuthorityRow>  $rows
     * @param  resource  $out
     * @return int the rows written, the header aside
     */
    public static function write(iterable $rows, $out): int
    {
        fwrite($out, "\u{FEFF}".self::line(self::HEADER));
        $count = 0;

        foreach ($rows as $row) {
            fwrite($out, self::line(self::cells($row)));
            $count++;
        }

        return $count;
    }

    /**
     * @return list<string>
     */
    private static function cells(AuthorityRow $row): array
    {
        $minutes = fn (int $value): array => [(string) $value, sprintf('%d:%02d', intdiv($value, 60), $value % 60)];

        return [
            $row->personName, $row->jobTitle, $row->localDate, $row->start ?? '', $row->end ?? '', $row->endDate ?? '', (string) $row->breakMinutes,
            ...$minutes($row->workedMinutes), ...$minutes($row->ordinaryMinutes),
            ...$minutes($row->doubleMinutes), ...$minutes($row->tripleMinutes), ...$minutes($row->restDayMinutes),
            $row->evidence, (string) count($row->corrections), implode('; ', $row->corrections), $row->confirmation, $row->weekDigest, implode('; ', $row->flags),
        ];
    }

    /**
     * @param  list<string>  $cells
     */
    private static function line(array $cells): string
    {
        return implode(',', array_map(self::cell(...), $cells))."\n";
    }

    private static function cell(string $value): string
    {
        $value = in_array(substr($value, 0, 1), ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;

        return strpbrk($value, ",\"\t\r\n") === false ? $value : '"'.str_replace('"', '""', $value).'"';
    }
}
