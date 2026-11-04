<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use App\Modules\Compensation\Data\CsvProblem;
use App\Modules\Compensation\Data\GradeSalaryData;

/** The tabulator's CSV: its columns, its template and what each row says. */
final class TabulatorCsv
{
    public const array COLUMNS = ['categoria', 'nivel', 'salario_diario', 'vigencia'];

    public static function template(): string
    {
        return CsvFile::template(self::COLUMNS, ['Operador', 'A', '524.65', '2026-01-01']);
    }

    /**
     * The salaries of the rows, or the problems of the ones that cannot be stored: nothing is imported unless there are none.
     * A category, level and vigencia that repeat are refused, since which salary wins would be a guess.
     *
     * @param  array<int, array<string, string>>  $rows  by spreadsheet row
     * @return array{salaries: list<GradeSalaryData>, problems: list<CsvProblem>}
     */
    public static function parse(array $rows): array
    {
        $salaries = [];
        $problems = [];
        $seen = [];

        foreach ($rows as $number => $row) {
            $category = $row['categoria'] ?? '';
            $level = $row['nivel'] ?? '';
            $cents = SalaryRules::pesos($row['salario_diario'] ?? '');
            $from = SalaryRules::date($row['vigencia'] ?? '');
            $salaryProblem = SalaryRules::salaryProblem($cents);
            $key = mb_strtolower(trim($category).'|'.trim($level).'|'.($from?->toDateString() ?? ''));
            $messages = array_values([
                ...SalaryRules::gradeProblems($category, $level),
                ...($salaryProblem === null ? [] : [$salaryProblem]),
                ...($from === null ? ['La vigencia debe ser una fecha AAAA-MM-DD o DD/MM/AAAA que exista.'] : []),
                ...($from !== null && isset($seen[$key]) ? ["Esta categoría, nivel y vigencia ya están en la fila {$seen[$key]}."] : []),
            ]);

            if ($messages !== []) {
                $problems[] = new CsvProblem($number, $messages);
            } elseif ($cents !== null && $from !== null) {
                $seen[$key] = $number;
                $salaries[] = new GradeSalaryData(trim($category), trim($level), $cents, $from);
            }
        }

        return ['salaries' => $salaries, 'problems' => $problems];
    }
}
