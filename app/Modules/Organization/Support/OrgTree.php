<?php

declare(strict_types=1);

namespace App\Modules\Organization\Support;

use App\Modules\Organization\Data\OrgFilters;
use App\Modules\Organization\Data\OrgNode;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

/**
 * The org chart as the screen reads it, a level at a time: who is at the top, who reports to a person and the chain
 * above one. The queries never grow with the number of people: one walk counts the team of every person of a page.
 *
 * The counts are the real size of a team (active people reached through active managers) and do not change with the
 * filters; with filters a person shows only when they or someone below them is within them.
 */
final readonly class OrgTree
{
    /** Active people with no manager, or whose manager left: nobody drops out of the tree. Whoever leads people first. */
    private const string ROOTS = <<<'SQL'
        candidates AS (
            SELECT e.id, row_number() OVER (ORDER BY EXISTS (
                SELECT 1 FROM employees r WHERE r.manager_id = e.id AND r.tenant_id = e.tenant_id AND r.deactivated_at IS NULL
            ) DESC, e.name, e.id) AS position
            FROM employees e
            LEFT JOIN employees m ON m.id = e.manager_id AND m.tenant_id = e.tenant_id
            WHERE e.tenant_id = :tenant AND e.deactivated_at IS NULL AND (m.id IS NULL OR m.deactivated_at IS NOT NULL)
        SQL;

    private const string CHILDREN = <<<'SQL'
        candidates AS (
            SELECT e.id, row_number() OVER (ORDER BY e.name, e.id) AS position
            FROM employees e
            WHERE e.tenant_id = :tenant AND e.deactivated_at IS NULL AND e.manager_id = :person
        SQL;

    /** From the person up, whoever left included; it stops on a cycle already in the data. */
    private const string CHAIN = <<<'SQL'
        line AS (
            SELECT e.id, e.manager_id, 0 AS height FROM employees e WHERE e.id = :person AND e.tenant_id = :tenant
            UNION ALL
            SELECT m.id, m.manager_id, line.height + 1 FROM line JOIN employees m ON m.id = line.manager_id AND m.tenant_id = :tenant
        ) CYCLE id SET is_cycle USING path,
        candidates AS (
            SELECT id, row_number() OVER (ORDER BY height DESC) AS position FROM line WHERE NOT is_cycle
        SQL;

    /** Each candidate's team, walked down through active people, and whether anyone in it is within the filters. */
    private const string WALK = <<<'SQL'
        ),
        walk AS (
            SELECT c.id AS top, e.id, e.work_center_id, e.area_id, 0 AS depth FROM candidates c JOIN employees e ON e.id = c.id
            UNION ALL
            SELECT w.top, e.id, e.work_center_id, e.area_id, w.depth + 1 FROM walk w
            JOIN employees e ON e.manager_id = w.id AND e.tenant_id = :tenant AND e.deactivated_at IS NULL
        ) CYCLE id SET is_cycle USING path,
        totals AS (
            SELECT top,
                   count(*) FILTER (WHERE depth = 1) AS direct_reports,
                   count(*) FILTER (WHERE depth > 0) AS total_reports,
                   bool_or((CAST(:center AS uuid) IS NULL OR work_center_id = CAST(:center AS uuid)) AND (CAST(:area AS uuid) IS NULL OR area_id = CAST(:area AS uuid))) AS subtree_matches,
                   bool_or(depth = 0 AND (CAST(:center AS uuid) IS NULL OR work_center_id = CAST(:center AS uuid)) AND (CAST(:area AS uuid) IS NULL OR area_id = CAST(:area AS uuid))) AS matches
            FROM walk WHERE NOT is_cycle GROUP BY top
        )
        SQL;

    private const string NODES = <<<'SQL'
        SELECT e.id, e.name, e.employee_number, e.job_title, e.work_center_id, a.name AS area,
               e.deactivated_at IS NULL AS active, t.direct_reports, t.total_reports, t.matches,
               COALESCE(m.deactivated_at IS NOT NULL, false) AS manager_inactive
        FROM totals t
        JOIN candidates c ON c.id = t.top
        JOIN employees e ON e.id = t.top
        LEFT JOIN areas a ON a.id = e.area_id
        LEFT JOIN employees m ON m.id = e.manager_id AND m.tenant_id = e.tenant_id
        WHERE t.subtree_matches
        ORDER BY c.position
        SQL;

    public function __construct(private CurrentTenant $tenant, private WorkCenters $workCenters) {}

    /**
     * @return LengthAwarePaginator<int, OrgNode>
     */
    public function roots(OrgFilters $filters, int $perPage = 50): LengthAwarePaginator
    {
        return $this->page(self::ROOTS, [], $filters, $perPage);
    }

    /**
     * The active direct reports of a person, by name.
     *
     * @return LengthAwarePaginator<int, OrgNode>
     */
    public function children(string $managerId, OrgFilters $filters, int $perPage = 50): LengthAwarePaginator
    {
        if (! Str::isUuid($managerId)) {
            return new LengthAwarePaginator([], 0, $perPage, 1, ['path' => LengthAwarePaginator::resolveCurrentPath()]);
        }

        return $this->page(self::CHILDREN, ['person' => $managerId], $filters, $perPage);
    }

    /**
     * From the top down to the person, who comes last; empty outside the company.
     *
     * @return list<OrgNode>
     */
    public function chain(string $employeeId): array
    {
        if (! Str::isUuid($employeeId)) {
            return [];
        }

        return $this->nodes(DB::select('WITH RECURSIVE '.self::CHAIN.self::WALK.self::NODES, $this->bindings(['person' => $employeeId], new OrgFilters)));
    }

    /**
     * A page of candidates as nodes. Without filters the page is cut before the walk; with them, after it, since
     * whether a person shows depends on their whole team (one walk of the company, at under 10 thousand people).
     *
     * @param  array<string, string>  $bindings
     * @return LengthAwarePaginator<int, OrgNode>
     */
    private function page(string $candidates, array $bindings, OrgFilters $filters, int $perPage): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();
        $cut = sprintf(' LIMIT %d OFFSET %d', $perPage, ($page - 1) * $perPage);
        $bindings = $this->bindings($bindings, $filters);
        $unfiltered = $filters->isEmpty();

        $total = DB::scalar('WITH RECURSIVE '.$candidates.self::WALK.($unfiltered ? 'SELECT count(*) FROM candidates' : 'SELECT count(*) FROM totals WHERE subtree_matches'), $bindings);
        $rows = DB::select('WITH RECURSIVE '.$candidates.($unfiltered ? ' ORDER BY position'.$cut : '').self::WALK.self::NODES.($unfiltered ? '' : $cut), $bindings);

        return new LengthAwarePaginator($this->nodes($rows), is_int($total) ? $total : 0, $perPage, $page, ['path' => LengthAwarePaginator::resolveCurrentPath()]);
    }

    /**
     * @param  array<string, string>  $bindings
     * @return array<string, string|null>
     */
    private function bindings(array $bindings, OrgFilters $filters): array
    {
        return [...$bindings, 'tenant' => $this->tenant->id(), 'center' => $filters->workCenterId, 'area' => $filters->areaId];
    }

    /**
     * @param  array<mixed>  $rows
     * @return list<OrgNode>
     */
    private function nodes(array $rows): array
    {
        $centers = array_column($this->workCenters->all(), 'name', 'id');

        return array_values(array_map(function (stdClass $row) use ($centers): OrgNode {
            $directReports = is_int($row->direct_reports) ? $row->direct_reports : 0;
            $name = $row->name ?? $row->employee_number;
            $center = is_string($row->work_center_id) ? $row->work_center_id : '';

            return new OrgNode(
                id: is_string($row->id) ? $row->id : '',
                name: is_string($name) ? $name : '',
                jobTitle: is_string($row->job_title) ? $row->job_title : '',
                area: is_string($row->area) ? $row->area : '',
                workCenter: $centers[$center] ?? '',
                active: $row->active === true,
                directReports: $directReports,
                totalReports: is_int($row->total_reports) ? $row->total_reports : 0,
                flag: SpanOfControl::flag($directReports),
                matches: $row->matches === true,
                managerInactive: $row->manager_inactive === true,
            );
        }, array_filter($rows, fn (mixed $row): bool => $row instanceof stdClass)));
    }
}
