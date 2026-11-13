<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use App\Modules\Compensation\Actions\CountBenefits;
use App\Modules\Compensation\Models\GradeSalary;
use App\Modules\Compensation\Models\SalaryOverride;
use App\Modules\Compensation\Models\WorkRiskPremium;
use App\Modules\Organization\Contracts\CompensationRoster;
use App\Modules\Organization\Contracts\JobGrades;
use Carbon\CarbonImmutable;

/**
 * Where the data of the contract stands (boards V4-Comp-Inicio and V4-Comp-Datos): the roster, the tabulator, the benefits, the
 * individual salaries and the work risk premium, each with what it lacks, and one notice of accuracy (C6): how many people the
 * cost covers and how many data would make it exact. Counts only: no salary and no person.
 */
final readonly class ContractStatus
{
    public function __construct(private CompensationRoster $roster, private JobGrades $jobGrades, private CountBenefits $benefits, private MinimumWageCheck $minimum) {}

    /**
     * @return array{roster: array{people: int, missing: int, without_grade: int, without_hire_date: int, without_group: int}, tabulator: array{grades: int, without_salary: int, below_minimum: int}, benefits: array{active: int}, overrides: array{people: int}, risk_premium: array{set: bool}, accuracy: array{costed: int, total: int, missing: int}}
     */
    public function handle(CarbonImmutable $today): array
    {
        $gaps = $this->roster->gaps();
        $grades = $this->jobGrades->page('', 1)->total();
        $paid = GradeSalary::query()->where('effective_from', '<=', $today->toDateString())->distinct()->count('job_grade_id');
        $premium = WorkRiskPremium::query()->where('effective_from', '<=', $today->toDateString())->exists();
        $missing = $gaps->total - $gaps->costed;

        return [
            'roster' => [
                'people' => $gaps->total, 'missing' => $missing,
                'without_grade' => $gaps->withoutGrade, 'without_hire_date' => $gaps->withoutHireDate, 'without_group' => $gaps->withoutGroup,
            ],
            'tabulator' => ['grades' => $grades, 'without_salary' => max(0, $grades - $paid), 'below_minimum' => $this->belowMinimum($today)],
            'benefits' => ['active' => $this->benefits->handle()['active']],
            'overrides' => ['people' => SalaryOverride::query()->distinct()->count('employee_number')],
            'risk_premium' => ['set' => $premium],
            'accuracy' => ['costed' => $gaps->costed, 'total' => $gaps->total, 'missing' => $missing + max(0, $grades - $paid) + ($premium ? 0 : 1)],
        ];
    }

    /** The categories whose salary in force today is under the minimum wage: the latest one that already started, of each. */
    private function belowMinimum(CarbonImmutable $today): int
    {
        return GradeSalary::query()
            ->where('effective_from', '<=', $today->toDateString())
            ->orderByDesc('effective_from')
            ->get(['job_grade_id', 'daily_salary_cents', 'effective_from'])
            ->unique('job_grade_id')
            ->filter(fn (GradeSalary $salary): bool => $this->minimum->inForce($salary->daily_salary_cents, CarbonImmutable::parse($salary->effective_from->toDateString()), $today) !== null)
            ->count();
    }
}
