<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Actions;

use App\Actions\RecordAudit;
use App\Modules\Compensation\Data\CsvProblem;
use App\Modules\Compensation\Data\ImportOutcome;
use App\Modules\Compensation\Data\OverrideData;
use App\Modules\Compensation\Models\SalaryOverride;
use App\Modules\Compensation\Support\CsvFile;
use App\Modules\Compensation\Support\OverridesCsv;
use App\Modules\Compensation\Support\OverrideTargets;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final readonly class ImportSalaryOverrides
{
    public function __construct(private CurrentTenant $currentTenant, private OverrideTargets $targets, private RecordAudit $audit) {}

    /**
     * Reads the individual salaries' CSV and, only if every row is valid and its person is an active one of the roster who
     * can be costed, writes all of it in one transaction: every row or none. A salary a person already has on that date is
     * replaced; the ones of other dates stay as history.
     *
     * @throws ValidationException when the file cannot be read at all (a column missing, no rows, too many)
     */
    public function handle(string $content): ImportOutcome
    {
        $read = CsvFile::read($content, OverridesCsv::COLUMNS);

        if ($read['error'] !== null) {
            throw ValidationException::withMessages(['file' => $read['error']]);
        }

        $parsed = OverridesCsv::parse($read['rows']);
        $target = $this->targets->resolve(array_values(array_unique(array_map(fn (OverrideData $data): string => $data->employeeNumber, $parsed['entries']))));
        $problems = $parsed['problems'];

        foreach ($parsed['entries'] as $row => $data) {
            if (isset($target['problems'][mb_strtolower($data->employeeNumber)])) {
                $problems[$row] = [$target['problems'][mb_strtolower($data->employeeNumber)]];
            }
        }

        if ($problems !== []) {
            ksort($problems);

            return new ImportOutcome(count($read['rows']), array_map(fn (int $row, array $messages): CsvProblem => new CsvProblem($row, $messages), array_keys($problems), $problems));
        }

        DB::transaction(function () use ($parsed, $target): void {
            foreach ($parsed['entries'] as $data) {
                SalaryOverride::query()->updateOrCreate(
                    ['employee_number' => $target['found'][mb_strtolower($data->employeeNumber)], 'effective_from' => $data->effectiveFrom->toDateString()],
                    ['tenant_id' => $this->currentTenant->id(), 'daily_salary_cents' => $data->dailySalaryCents, 'note' => $data->note],
                );
            }

            // Counts only: who earns what stays out of the log and the audit trail.
            Log::info('compensation.overrides_imported', ['rows' => count($parsed['entries'])]);
            $this->audit->handle('compensation.overrides_imported', null, ['overrides' => count($parsed['entries'])]);
        });

        return new ImportOutcome(count($read['rows']));
    }
}
