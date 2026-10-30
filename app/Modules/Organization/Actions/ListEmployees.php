<?php

declare(strict_types=1);

namespace App\Modules\Organization\Actions;

use App\Modules\Organization\Data\EmployeeFilters;
use App\Modules\Organization\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListEmployees
{
    public const int PER_PAGE = 25;

    /**
     * The current company's employees that match the filters, by name, a page at a time, with their area.
     *
     * @return LengthAwarePaginator<int, Employee>
     */
    public function handle(EmployeeFilters $filters): LengthAwarePaginator
    {
        // LIKE's own wildcards in what was typed are searched as themselves.
        $like = '%'.addcslashes($filters->search, '\\%_').'%';

        return Employee::query()
            ->with('area:id,name')
            ->when($filters->active, fn (Builder $query) => $query->whereNull('deactivated_at'), fn (Builder $query) => $query->whereNotNull('deactivated_at'))
            ->when($filters->workCenterId !== null, fn (Builder $query) => $query->where('work_center_id', $filters->workCenterId))
            ->when($filters->areaId !== null, fn (Builder $query) => $query->where('area_id', $filters->areaId))
            ->when($filters->search !== '', fn (Builder $query) => $query->where(fn (Builder $match) => $match
                ->where('name', 'ilike', $like)
                ->orWhere('email', 'ilike', $like)
                ->orWhere('employee_number', 'ilike', $like)))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }
}
