<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use App\Modules\Compensation\Data\OverrideData;

/** The individual salaries' CSV: its columns, its template and what each row says, before the roster is asked about anyone. */
final class OverridesCsv
{
    /** The columns a file must have; a `nota` column is read when it is there. */
    public const array COLUMNS = ['id_de_la_empresa', 'salario_diario', 'vigencia'];

    public static function template(): string
    {
        return CsvFile::template(self::COLUMNS, ['A-001', '650.25', '2026-01-01']);
    }

    /**
     * The salaries of the rows that are valid by themselves, and the problems of the others, both by spreadsheet row. A
     * person who appears twice is refused from the second time on, since which salary wins would be a guess.
     *
     * @param  array<int, array<string, string>>  $rows  by spreadsheet row
     * @return array{entries: array<int, OverrideData>, problems: array<int, list<string>>}
     */
    public static function parse(array $rows): array
    {
        $entries = [];
        $problems = [];
        $seen = [];

        foreach ($rows as $number => $row) {
            $id = $row['id_de_la_empresa'] ?? '';
            $key = mb_strtolower($id);
            $cents = SalaryRules::pesos($row['salario_diario'] ?? '');
            $from = SalaryRules::date($row['vigencia'] ?? '');
            $note = ($row['nota'] ?? '') === '' ? null : $row['nota'];
            $salaryProblem = SalaryRules::salaryProblem($cents);
            $messages = [
                ...($id === '' || mb_strlen($id) > SalaryRules::NUMBER_LENGTH ? ['Escribe el ID de la empresa de la persona, de '.SalaryRules::NUMBER_LENGTH.' caracteres a lo más.'] : []),
                ...($salaryProblem === null ? [] : [$salaryProblem]),
                ...($from === null ? ['La vigencia debe ser una fecha AAAA-MM-DD o DD/MM/AAAA que exista.'] : []),
                ...($note !== null && mb_strlen($note) > SalaryRules::NOTE_LENGTH ? ['La nota puede tener '.SalaryRules::NOTE_LENGTH.' caracteres a lo más.'] : []),
                ...($id !== '' && isset($seen[$key]) ? ["Este ID de la empresa ya está en la fila {$seen[$key]}."] : []),
            ];

            if ($id !== '') {
                $seen[$key] ??= $number;
            }

            if ($messages !== []) {
                $problems[$number] = $messages;
            } elseif ($cents !== null && $from !== null) {
                $entries[$number] = new OverrideData($id, $cents, $from, $note);
            }
        }

        return ['entries' => $entries, 'problems' => $problems];
    }
}
