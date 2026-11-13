<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Data;

/** What came of a file: how many rows it had and the problems that kept it from being imported (none when it was). */
final readonly class ImportOutcome
{
    /**
     * @param  list<CsvProblem>  $problems
     * @param  list<int>  $lowRows  rows imported with a salary under the general minimum: a warning, not an error
     */
    public function __construct(public int $rows, public array $problems = [], public array $lowRows = []) {}

    /** What to tell about the rows imported under the minimum wage; null when there are none. */
    public function lowSalaryNote(): ?string
    {
        $rows = $this->lowRows;

        if ($rows === []) {
            return null;
        }

        $last = array_pop($rows);
        $list = $rows === [] ? "fila {$last}" : 'filas '.implode(', ', $rows)." y {$last}";

        return "Revisa la {$list}: ".(count($this->lowRows) === 1 ? 'está' : 'están').' por debajo del salario mínimo general.';
    }

    public function imported(): bool
    {
        return $this->problems === [];
    }

    /**
     * What the page shows of a file that was not imported.
     *
     * @return array{status: string, rows: int, errors: list<array{row: int, messages: list<string>}>}
     */
    public function summary(): array
    {
        return [
            'status' => $this->imported() ? 'imported' : 'invalid',
            'rows' => $this->rows,
            // The first hundred are enough to fix a file: the rest usually repeat the same mistake.
            'errors' => array_map(fn (CsvProblem $problem): array => ['row' => $problem->row, 'messages' => $problem->messages], array_slice($this->problems, 0, 100)),
        ];
    }
}
