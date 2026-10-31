<?php

declare(strict_types=1);

namespace App\Modules\Organization\Support;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Organization\Data\RosterQuery;
use App\Modules\Organization\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use LogicException;

final class CompanyRoster implements Roster
{
    public function activeIn(string $workCenterId): array
    {
        return array_values($this->query()
            ->where('work_center_id', $workCenterId)
            ->whereNull('deactivated_at')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map($this->entry(...))
            ->all());
    }

    public function find(string $employeeId): ?RosterEntry
    {
        $employee = $this->query()->find($employeeId);

        return $employee === null ? null : $this->entry($employee);
    }

    public function findMany(array $employeeIds): array
    {
        return $this->query()->among('employees.id', $employeeIds)->get()->mapWithKeys(fn (Employee $employee): array => [$employee->id => $this->entry($employee)])->all();
    }

    public function page(RosterQuery $query, int $perPage = 25, string $pageName = 'page'): LengthAwarePaginator
    {
        return $this->matching($query)->with('area')->paginate($perPage, pageName: $pageName)->withQueryString()->through($this->entry(...));
    }

    public function matchingIds(RosterQuery $query): array
    {
        return array_values($this->matching($query)->get(['id'])->map(fn (Employee $employee): string => $employee->id)->all());
    }

    /**
     * @return Builder<Employee>
     */
    private function matching(RosterQuery $query): Builder
    {
        return Employee::query()
            ->when($query->employeeIds !== null, fn (Builder $employees) => $employees->among('employees.id', $query->employeeIds ?? []))
            ->when($query->activeInWorkCenter !== null, fn (Builder $employees) => $employees->where('work_center_id', $query->activeInWorkCenter)->whereNull('deactivated_at'))
            ->when($query->areaId !== null, fn (Builder $employees) => $employees->where('area_id', $query->areaId))
            ->matching(['name', 'email', 'employee_number'], $query->search)
            ->orderBy('name')
            ->orderBy('id');
    }

    /**
     * @return Builder<Employee>
     */
    private function query(): Builder
    {
        return Employee::query()->with('area');
    }

    private function entry(Employee $employee): RosterEntry
    {
        // The foreign key makes the area required; a missing one is broken data, never an empty name.
        $area = $employee->area ?? throw new LogicException("Employee [{$employee->id}] has no area.");

        return new RosterEntry(
            $employee->id,
            $employee->name,
            $employee->email,
            $employee->gender->value,
            $employee->area_id,
            $area->name,
            $employee->work_center_id,
            $employee->deactivated_at === null,
            $employee->job_title,
        );
    }
}
