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

    /**
     * @return array{without_manager: int, roots: int, inactive_manager: int}
     */
    public function counts(): array
    {
        $counts = $this->active()->toBase()->selectRaw(sprintf(
            'count(*) FILTER (WHERE manager_id IS NULL AND NOT %1$s) AS without_manager, count(*) FILTER (WHERE manager_id IS NULL AND %1$s) AS roots, count(*) FILTER (WHERE %2$s) AS inactive_manager',
            self::HAS_REPORTS,
            self::MANAGER_LEFT,
        ))->first();
        $count = fn (string $key): int => is_int($counts?->{$key}) ? $counts->{$key} : 0;

        return ['without_manager' => $count('without_manager'), 'roots' => $count('roots'), 'inactive_manager' => $count('inactive_manager')];
    }

    /**
     * The people of an issue by name, each with their area and their manager.
     *
     * @return LengthAwarePaginator<int, Employee>
     */
    public function page(ReportingIssue $issue, int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->active();

        match ($issue) {
            ReportingIssue::WithoutManager => $query->whereNull('manager_id')->whereRaw('NOT '.self::HAS_REPORTS),
            ReportingIssue::ExtraRoot => $query->whereNull('manager_id')->whereRaw(self::HAS_REPORTS),
            ReportingIssue::InactiveManager => $query->whereRaw(self::MANAGER_LEFT),
        };

        return $query
            ->with(['area:id,name', 'manager:id,name,employee_number,job_title'])
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
