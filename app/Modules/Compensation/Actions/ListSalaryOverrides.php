<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Actions;

use App\Modules\Compensation\Models\SalaryOverride;
use App\Modules\Compensation\Support\BenefitValue;
use App\Modules\Compensation\Support\Calculation\Money;
use App\Modules\Compensation\Support\MinimumWageCheck;
use App\Modules\Organization\Contracts\CompensationRoster;
use App\Modules\Organization\Contracts\JobGrades;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Expression;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListSalaryOverrides
{
    public const int PER_PAGE = 15;

    public function __construct(private CompensationRoster $roster, private JobGrades $jobGrades, private MinimumWageCheck $minimum) {}

    /**
     * A page of the people with an individual salary (the ones whose employee number or notes have what was typed, ignoring
     * case and accents), by employee number. Each comes with their salaries from the latest vigencia to the oldest, the
     * one in force on `$today`, and their category and level when they are still in the roster and can be costed.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function handle(string $search, CarbonImmutable $today): LengthAwarePaginator
    {
        $page = SalaryOverride::query()
            ->matching(['employee_number', 'note'], $search)
            ->selectRaw('min(employee_number) as employee_number')
            ->groupByRaw('lower(employee_number)')
            ->orderByRaw('lower(employee_number)')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
        $numbers = array_values(array_map(fn (SalaryOverride $person): string => $person->employee_number, $page->items()));
        $byPerson = [];

        foreach (SalaryOverride::query()->whereIn(new Expression('lower(employee_number)'), array_map(mb_strtolower(...), $numbers))->orderByDesc('effective_from')->get() as $override) {
            $byPerson[mb_strtolower($override->employee_number)][] = $override;
        }

        $people = $this->roster->individuals($numbers);
        $grades = $this->jobGrades->findMany(array_values(array_unique(array_map(fn ($person): string => $person->jobGradeId, $people))));
        $roster = [];

        foreach ($people as $number => $person) {
            $roster[mb_strtolower($number)] = isset($grades[$person->jobGradeId]) ? "{$grades[$person->jobGradeId]->category} · {$grades[$person->jobGradeId]->level}" : null;
        }

        return $page->through(fn (SalaryOverride $person): array => $this->row($person->employee_number, $byPerson[mb_strtolower($person->employee_number)] ?? [], array_key_exists(mb_strtolower($person->employee_number), $roster), $roster[mb_strtolower($person->employee_number)] ?? null, $today->toDateString()));
    }

    /**
     * @param  list<SalaryOverride>  $overrides  the latest vigencia first
     * @return array<string, mixed>
     */
    private function row(string $number, array $overrides, bool $active, ?string $grade, string $today): array
    {
        $current = array_find($overrides, fn (SalaryOverride $override): bool => $override->effective_from->toDateString() <= $today);

        return [
            'employee_number' => $number,
            'grade' => $grade,
            'active' => $active,
            'current' => $current === null ? null : $this->salary($current, CarbonImmutable::parse($today)),
            'salaries' => array_map(fn (SalaryOverride $override): array => ['id' => $override->id, ...$this->salary($override)], $overrides),
        ];
    }

    /**
     * @return array{daily_salary: string, daily_salary_text: string, effective_from: string, note: ?string, below_minimum: string|null}
     */
    private function salary(SalaryOverride $override, ?CarbonImmutable $inForceOn = null): array
    {
        return [
            'daily_salary' => BenefitValue::decimal($override->daily_salary_cents),
            'daily_salary_text' => Money::format($override->daily_salary_cents),
            'effective_from' => $override->effective_from->toDateString(),
            'note' => $override->note,
            'below_minimum' => $inForceOn === null
                ? $this->minimum->warning($override->daily_salary_cents, CarbonImmutable::parse($override->effective_from->toDateString()))
                : $this->minimum->inForce($override->daily_salary_cents, CarbonImmutable::parse($override->effective_from->toDateString()), $inForceOn),
        ];
    }
}
