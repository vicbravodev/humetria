<?php

declare(strict_types=1);

namespace App\Modules\Organization\Support;

use App\Modules\Organization\Enums\ReportingIssue;
use App\Modules\Organization\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The gaps of the reporting line among active people: how many of each, and who they are, a page at a time.
 */
final readonly class ReportingGaps
{
    private const string HAS_REPORTS = 'EXISTS (SELECT 1 FROM employees r WHERE r.manager_id = employees.id AND r.tenant_id = employees.tenant_id AND r.deactivated_at IS NULL)';

    private const string MANAGER_LEFT = 'EXISTS (SELECT 1 FROM employees m WHERE m.id = employees.manager_id AND m.tenant_id = employees.tenant_id AND m.deactivated_at IS NOT NULL)';

    /** More active direct reports than a manager can lead well: SpanOfControl::MANY_MIN or more. */
    private const string MANY_REPORTS = '(SELECT count(*) FROM employees r WHERE r.manager_id = employees.id AND r.tenant_id = employees.tenant_id AND r.deactivated_at IS NULL) >= %d';

    /**
     * @return array{all: int, no_manager: int, many_reports: int, inactive_manager: int, without_manager: int}
     */
    public function counts(): array
    {
        $counts = $this->active()->toBase()->selectRaw(sprintf(
            'count(*) AS "all", count(*) FILTER (WHERE manager_id IS NULL) AS no_manager, count(*) FILTER (WHERE %3$s) AS many_reports, count(*) FILTER (WHERE %2$s) AS inactive_manager, count(*) FILTER (WHERE manager_id IS NULL AND NOT %1$s) AS without_manager',
            self::HAS_REPORTS,
            self::MANAGER_LEFT,
            sprintf(self::MANY_REPORTS, SpanOfControl::MANY_MIN),
        ))->first();
        $count = fn (string $key): int => is_int($counts?->{$key}) ? $counts->{$key} : 0;

        return [
            'all' => $count('all'),
            'no_manager' => $count('no_manager'),
            'many_reports' => $count('many_reports'),
            'inactive_manager' => $count('inactive_manager'),
            'without_manager' => $count('without_manager'),
        ];
    }

    /**
     * The people of a view, each with their area, their manager and how many people report to them; those whose name
     * or employee number has what was typed, and who work in the center, when either was asked. Whoever has no manager
     * comes first on the full list: it is what asks for action.
     *
     * @return LengthAwarePaginator<int, Employee>
     */
    public function page(ReportingIssue $issue, int $perPage = 25, string $search = '', ?string $workCenterId = null): LengthAwarePaginator
    {
        $query = $this->active()
            ->matching(['name', 'employee_number'], $search)
            ->when($workCenterId !== null, fn (Builder $query) => $query->where('work_center_id', $workCenterId));

        match ($issue) {
            ReportingIssue::All => $query->orderByRaw('manager_id IS NOT NULL'),
            ReportingIssue::NoManager => $query->whereNull('manager_id'),
            ReportingIssue::WithoutManager => $query->whereNull('manager_id')->whereRaw('NOT '.self::HAS_REPORTS),
            ReportingIssue::ManyReports => $query->whereRaw(sprintf(self::MANY_REPORTS, SpanOfControl::MANY_MIN)),
            ReportingIssue::InactiveManager => $query->whereRaw(self::MANAGER_LEFT),
        };

        return $query
            ->with(['area:id,name', 'manager:id,name,employee_number,job_title,deactivated_at'])
            ->withCount(['reports as direct_reports' => fn (Builder $reports) => $reports->whereNull('deactivated_at')])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return Builder<Employee>
     */
    private function active(): Builder
    {
        return Employee::query()->whereNull('deactivated_at');
    }
}
