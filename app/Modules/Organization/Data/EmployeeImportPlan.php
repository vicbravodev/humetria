<?php

declare(strict_types=1);

namespace App\Modules\Organization\Data;

/**
 * What an import would do: the rows it would write, or the problems of each row. All or nothing: with a single
 * problem, nothing is written.
 */
final readonly class EmployeeImportPlan
{
    /**
     * @param  list<EmployeeImportRow>  $rows
     * @param  list<array{row: int, messages: list<string>}>  $errors  `row` as the spreadsheet numbers it (the header is 1).
     */
    public function __construct(
        public int $total,
        public array $rows,
        public array $errors,
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function created(): int
    {
        return count(array_filter($this->rows, fn (EmployeeImportRow $row): bool => $row->existingId === null));
    }

    public function updated(): int
    {
        return count($this->rows) - $this->created();
    }

    /**
     * @return array{status: string, rows: int, created: int, updated: int, errors: list<array{row: int, messages: list<string>}>}
     */
    public function summary(): array
    {
        return [
            'status' => $this->isValid() ? 'ready' : 'invalid',
            'rows' => $this->total,
            'created' => $this->isValid() ? $this->created() : 0,
            'updated' => $this->isValid() ? $this->updated() : 0,
            // The first hundred are enough to fix a file: the rest usually repeat the same mistake.
            'errors' => array_slice($this->errors, 0, 100),
        ];
    }
}
