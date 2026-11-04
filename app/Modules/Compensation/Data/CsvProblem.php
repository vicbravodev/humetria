<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Data;

/** What is wrong with one row of a file, which is numbered as the spreadsheet numbers it. */
final readonly class CsvProblem
{
    /**
     * @param  list<string>  $messages
     */
    public function __construct(public int $row, public array $messages) {}
}
