<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Actions;

use App\Actions\RecordAudit;
use App\Modules\Compensation\Data\ImportOutcome;
use App\Modules\Compensation\Models\GradeSalary;
use App\Modules\Compensation\Support\CsvFile;
use App\Modules\Compensation\Support\TabulatorCsv;
use App\Modules\Organization\Contracts\JobGrades;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final readonly class ImportTabulator
{
    public function __construct(private CurrentTenant $currentTenant, private JobGrades $jobGrades, private RecordAudit $audit) {}

    /**
     * Reads the tabulator's CSV and, only if every row is valid, writes all of it in one transaction: every row or none,
     * the missing categories and levels included. A salary a grade already has on that date is replaced.
     *
     * @throws ValidationException when the file cannot be read at all (a column missing, no rows, too many)
     */
    public function handle(string $content): ImportOutcome
    {
        $read = CsvFile::read($content, ['categoria', 'nivel', 'salario_diario', 'vigencia']);

        if ($read['error'] !== null) {
            throw ValidationException::withMessages(['file' => $read['error']]);
        }

        $parsed = TabulatorCsv::parse($read['rows']);

        if ($parsed['problems'] !== []) {
            return new ImportOutcome(count($read['rows']), $parsed['problems']);
        }

        DB::transaction(function () use ($parsed): void {
            /** One lookup per category and level, not per row. */
            $grades = [];

            foreach ($parsed['salaries'] as $salary) {
                $grade = $grades[mb_strtolower($salary->category.'|'.$salary->level)] ??= $this->jobGrades->findOrCreate($salary->category, $salary->level)->id;
                GradeSalary::query()->updateOrCreate(
                    ['job_grade_id' => $grade, 'effective_from' => $salary->effectiveFrom->toDateString()],
                    ['tenant_id' => $this->currentTenant->id(), 'daily_salary_cents' => $salary->dailySalaryCents],
                );
            }

            // Counts only: the salaries, and who earns them, stay out of the log and the audit trail.
            $counts = ['rows' => count($parsed['salaries']), 'grades' => count($grades)];
            Log::info('compensation.tabulator_imported', $counts);
            $this->audit->handle('compensation.tabulator_imported', null, $counts);
        });

        return new ImportOutcome(count($read['rows']));
    }
}
