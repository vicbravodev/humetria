<?php

declare(strict_types=1);

namespace App\Modules\Organization\Support;

use App\Modules\Organization\Contracts\CompensationRoster;
use App\Modules\Organization\Data\CompensationPerson;
use App\Modules\Organization\Data\HeadcountCell;
use App\Modules\Organization\Data\RosterGaps;
use App\Modules\Organization\Enums\StaffGroup;
use App\Modules\Organization\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CompanyCompensationRoster implements CompensationRoster
{
    public function cells(CarbonImmutable $asOf, array $excludingNumbers): array
    {
        $query = $this->costable()->where('hired_on', '<=', $asOf->toDateString());

        if ($excludingNumbers !== []) {
            // A person without a number is never excluded: NOT IN alone would drop them (NULL is neither in nor out).
            $query->where(fn (Builder $builder) => $builder->whereNull('employee_number')
                ->orWhereNotIn(DB::raw('lower(employee_number)'), array_map(Str::lower(...), $excludingNumbers)));
        }

        $rows = $query
            ->selectRaw("job_grade_id, area_id, staff_group, state, to_char(date_trunc('month', hired_on::timestamp), 'YYYY-MM-DD') as hire_month, count(*) as people")
            ->groupBy('job_grade_id', 'area_id', 'staff_group', 'state')
            ->groupByRaw("date_trunc('month', hired_on::timestamp)")
            ->orderBy('hire_month')
            ->orderBy('job_grade_id')
            ->orderBy('area_id')
            ->orderBy('staff_group')
            ->orderBy('state')
            ->toBase()
            ->get();

        $cells = [];

        foreach ($rows as $row) {
            $group = is_string($row->staff_group) ? StaffGroup::tryFrom($row->staff_group) : null;

            if (is_string($row->job_grade_id) && $group !== null && is_string($row->hire_month) && is_int($row->people) && $row->people > 0) {
                $cells[] = new HeadcountCell(
                    $row->job_grade_id,
                    is_string($row->area_id) ? $row->area_id : null,
                    $group,
                    is_string($row->state) ? $row->state : null,
                    CarbonImmutable::parse($row->hire_month, 'UTC'),
                    $row->people,
                );
            }
        }

        return $cells;
    }

    public function individuals(array $employeeNumbers): array
    {
        if ($employeeNumbers === []) {
            return [];
        }

        $people = [];
        $rows = $this->costable()
            ->whereIn(DB::raw('lower(employee_number)'), array_map(Str::lower(...), $employeeNumbers))
            ->toBase()
            ->get(['employee_number', 'job_grade_id', 'area_id', 'staff_group', 'state', 'hired_on']);

        foreach ($rows as $row) {
            $group = is_string($row->staff_group) ? StaffGroup::tryFrom($row->staff_group) : null;

            if (is_string($row->employee_number) && is_string($row->job_grade_id) && $group !== null && is_string($row->hired_on)) {
                $people[$row->employee_number] = new CompensationPerson(
                    $row->employee_number,
                    $row->job_grade_id,
                    is_string($row->area_id) ? $row->area_id : null,
                    $group,
                    is_string($row->state) ? $row->state : null,
                    CarbonImmutable::parse($row->hired_on, 'UTC'),
                );
            }
        }

        return $people;
    }

    public function numbered(CarbonImmutable $asOf): iterable
    {
        $rows = $this->costable()
            ->whereNotNull('employee_number')
            ->where('hired_on', '<=', $asOf->toDateString())
            ->orderBy('employee_number')
            ->select(['employee_number', 'job_grade_id', 'area_id', 'staff_group', 'state', 'hired_on'])
            ->toBase()
            ->cursor();

        foreach ($rows as $row) {
            $group = is_string($row->staff_group) ? StaffGroup::tryFrom($row->staff_group) : null;

            if (is_string($row->employee_number) && is_string($row->job_grade_id) && $group !== null && is_string($row->hired_on)) {
                yield new CompensationPerson(
                    $row->employee_number,
                    $row->job_grade_id,
                    is_string($row->area_id) ? $row->area_id : null,
                    $group,
                    is_string($row->state) ? $row->state : null,
                    CarbonImmutable::parse($row->hired_on, 'UTC'),
                );
            }
        }
    }

    public function gaps(): RosterGaps
    {
        $active = Employee::query()->whereNull('deactivated_at');
        $incomplete = (clone $active)->whereNot(fn (Builder $query) => $this->complete($query));

        $missing = [];

        foreach ((clone $incomplete)->whereNotNull('employee_number')->orderBy('employee_number')->pluck('employee_number') as $number) {
            if (is_string($number)) {
                $missing[] = $number;
            }
        }

        $total = (clone $active)->count();
        $missingWithoutNumber = (clone $incomplete)->whereNull('employee_number')->count();

        return new RosterGaps(
            total: $total,
            costed: $total - count($missing) - $missingWithoutNumber,
            missing: $missing,
            missingWithoutNumber: $missingWithoutNumber,
            inactive: Employee::query()->whereNotNull('deactivated_at')->count(),
        );
    }

    /**
     * @return Builder<Employee>
     */
    private function costable(): Builder
    {
        return Employee::query()->whereNull('deactivated_at')->where(fn (Builder $query) => $this->complete($query));
    }

    /**
     * @param  Builder<Employee>  $query
     */
    private function complete(Builder $query): void
    {
        $query->whereNotNull('job_grade_id')->whereNotNull('hired_on')->whereNotNull('staff_group');
    }
}
