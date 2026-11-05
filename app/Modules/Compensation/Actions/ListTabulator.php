<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Actions;

use App\Modules\Compensation\Models\GradeSalary;
use App\Modules\Compensation\Support\BenefitValue;
use App\Modules\Compensation\Support\Calculation\Money;
use App\Modules\Organization\Contracts\JobGrades;
use App\Modules\Organization\Data\JobGradeData;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListTabulator
{
    public const int PER_PAGE = 15;

    public function __construct(private JobGrades $jobGrades) {}

    /**
     * A page of the company's categories and levels (the ones that match what was typed, ignoring case and accents), each
     * with its salaries from the latest vigencia to the oldest and the one in force on `$today`. A grade without a salary
     * is there too: it is what the tabulator still lacks.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function handle(string $search, CarbonImmutable $today): LengthAwarePaginator
    {
        $page = $this->jobGrades->page($search, self::PER_PAGE)->withQueryString();
        $byGrade = [];

        foreach (GradeSalary::query()->whereIn('job_grade_id', array_map(fn (JobGradeData $grade): string => $grade->id, $page->items()))->orderByDesc('effective_from')->get() as $salary) {
            $byGrade[$salary->job_grade_id][] = $salary;
        }

        return $page->through(fn (JobGradeData $grade): array => $this->row($grade, $byGrade[$grade->id] ?? [], $today->toDateString()));
    }

    /**
     * @param  list<GradeSalary>  $salaries  the latest vigencia first
     * @return array<string, mixed>
     */
    private function row(JobGradeData $grade, array $salaries, string $today): array
    {
        $current = array_find($salaries, fn (GradeSalary $salary): bool => $salary->effective_from->toDateString() <= $today);

        return [
            'id' => $grade->id,
            'category' => $grade->category,
            'level' => $grade->level,
            'current' => $current === null ? null : self::salary($current),
            'salaries' => array_map(fn (GradeSalary $salary): array => ['id' => $salary->id, ...self::salary($salary)], $salaries),
        ];
    }

    /**
     * @return array{daily_salary: string, daily_salary_text: string, effective_from: string}
     */
    private static function salary(GradeSalary $salary): array
    {
        return [
            'daily_salary' => BenefitValue::decimal($salary->daily_salary_cents),
            'daily_salary_text' => Money::format($salary->daily_salary_cents),
            'effective_from' => $salary->effective_from->toDateString(),
        ];
    }
}
