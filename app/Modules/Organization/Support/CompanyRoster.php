<?php

declare(strict_types=1);

namespace App\Modules\Organization\Support;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Organization\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
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
        return $this->query()->whereKey($employeeIds)->get()->mapWithKeys(fn (Employee $employee): array => [$employee->id => $this->entry($employee)])->all();
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
        );
    }
}
