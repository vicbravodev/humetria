<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Actions;

use App\Actions\RecordAudit;
use App\Modules\Compensation\Enums\PayGroup;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Compensation\Support\Calculation\ScenarioCost;
use App\Modules\Compensation\Support\ScenarioInputBuilder;
use App\Modules\Organization\Contracts\CompensationRoster;
use App\Modules\Organization\Contracts\JobGrades;
use Illuminate\Support\Facades\Log;

final readonly class ExportPersonAnnex
{
    private const int DAYS_IN_YEAR = 365;

    public const array HEADER = ['id_de_la_empresa', 'categoria', 'nivel', 'grupo', 'fecha_de_ingreso', 'sueldo_individual', 'salario_diario_actual', 'salario_diario_propuesto', 'cambio_anual_en_salario'];

    public function __construct(private ScenarioInputBuilder $inputs, private CompensationRoster $roster, private JobGrades $jobGrades, private RecordAudit $audit) {}

    /**
     * The rows of the annex of a scenario, one per person by the company's employee number, with the salary today, the one the
     * proposal gives and what that changes in a year. Whoever has no employee number or no salary is left out: nobody is told
     * apart by anything else. Each export goes to the log and the audit trail with the count and no person or salary.
     *
     * @return list<list<string>> the header first
     */
    public function handle(Scenario $scenario): array
    {
        $input = $this->inputs->build($scenario);
        $own = [];

        foreach ($input->individuals as $individual) {
            $own[mb_strtolower($individual->person->employeeNumber)] = $individual->dailySalaryCents;
        }

        $rows = [self::HEADER];
        $grades = $this->gradeNames(array_keys($input->gradeSalaries));

        foreach ($this->roster->numbered($scenario->effective_on) as $person) {
            $salary = $own[mb_strtolower($person->employeeNumber)] ?? $input->gradeSalaries[$person->jobGradeId] ?? null;

            if ($salary === null) {
                continue;
            }

            $group = PayGroup::from($person->group->value);
            $proposed = ScenarioCost::raised($salary, $group, $input);
            $rows[] = [
                $this->safe($person->employeeNumber), $grades[$person->jobGradeId][0] ?? '', $grades[$person->jobGradeId][1] ?? '', $group->label(),
                $person->hiredOn->toDateString(), isset($own[mb_strtolower($person->employeeNumber)]) ? 'Sí' : 'No',
                $this->pesos($salary), $this->pesos($proposed), $this->pesos(($proposed - $salary) * self::DAYS_IN_YEAR),
            ];
        }

        Log::info('compensation.annex_exported', ['scenario_id' => $scenario->id, 'people' => count($rows) - 1]);
        $this->audit->handle('compensation.annex_exported', null, ['scenario_id' => $scenario->id, 'people' => count($rows) - 1]);

        return $rows;
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, array{string, string}> category and level by grade id
     */
    private function gradeNames(array $ids): array
    {
        $names = [];

        foreach ($this->jobGrades->findMany($ids) as $id => $grade) {
            $names[$id] = [$this->safe($grade->category), $this->safe($grade->level)];
        }

        return $names;
    }

    /** A spreadsheet runs a cell that starts with = + - or @ as a formula; a quote in front makes it text. */
    private function safe(string $text): string
    {
        return $text !== '' && str_contains('=+-@', $text[0]) ? "'{$text}" : $text;
    }

    private function pesos(int $cents): string
    {
        return ($cents < 0 ? '-' : '').intdiv(abs($cents), 100).'.'.sprintf('%02d', abs($cents) % 100);
    }
}
