<?php

declare(strict_types=1);

namespace App\Modules\Organization\Support;

use App\Modules\Organization\Contracts\ReportingLines;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One query per answer. The walks stop on a cycle already in the data (a restore with the trigger disabled), so they
 * always end; the recursive one names the company inside its SQL (the tenant scope does not reach a recursive query).
 */
final readonly class CompanyReportingLines implements ReportingLines
{
    public function __construct(private CurrentTenant $tenant) {}

    public function managerOf(string $employeeId): ?string
    {
        if (! Str::isUuid($employeeId)) {
            return null;
        }

        $managerId = Employee::query()->whereKey($employeeId)->value('manager_id');

        return is_string($managerId) ? $managerId : null;
    }

    public function chainOf(string $employeeId): array
    {
        return $this->chainsOf([$employeeId])[$employeeId];
    }

    public function chainsOf(array $employeeIds): array
    {
        // The whole company's line at once (two columns): walking it here is cheaper than one recursive query per person.
        $managers = array_filter(Employee::query()->pluck('manager_id', 'id')->all(), is_string(...));
        $chains = [];

        foreach ($employeeIds as $employeeId) {
            $chain = [];
            $seen = [$employeeId => true];
            $manager = $managers[$employeeId] ?? null;

            // Up to the top, stopping on a cycle already in the data (a restore with the trigger disabled).
            while ($manager !== null && ! isset($seen[$manager])) {
                $seen[$manager] = true;
                $chain[] = $manager;
                $manager = $managers[$manager] ?? null;
            }

            $chains[$employeeId] = $chain;
        }

        return $chains;
    }

    public function directReportIds(string $employeeId): array
    {
        if (! Str::isUuid($employeeId)) {
            return [];
        }

        return array_values(array_filter(Employee::query()
            ->where('manager_id', $employeeId)
            ->whereNull('deactivated_at')
            ->orderBy('name')
            ->orderBy('id')
            ->pluck('id')
            ->all(), is_string(...)));
    }

    public function peersOf(array $employeeIds): array
    {
        $ids = array_values(array_filter($employeeIds, Str::isUuid(...)));

        if ($ids === []) {
            return [];
        }

        $rows = DB::table('employees as person')
            ->join('employees as peer', fn (JoinClause $join) => $join->on('peer.manager_id', '=', 'person.manager_id')->on('peer.tenant_id', '=', 'person.tenant_id'))
            ->where('person.tenant_id', $this->tenant->id())
            ->whereIn('person.id', $ids)
            ->whereColumn('peer.id', '<>', 'person.id')
            ->whereNull('peer.deactivated_at')
            ->orderBy('peer.name')
            ->orderBy('peer.id')
            ->get(['person.id as person_id', 'peer.id as peer_id']);

        $peers = [];

        foreach ($rows as $row) {
            if (is_string($row->person_id) && is_string($row->peer_id)) {
                $peers[$row->person_id][] = $row->peer_id;
            }
        }

        return $peers;
    }

    public function subtreeIds(string $employeeId): array
    {
        return $this->walk(<<<'SQL'
            WITH RECURSIVE line AS (
                SELECT id, deactivated_at, 1 AS depth FROM employees WHERE manager_id = :person AND tenant_id = :tenant
                UNION ALL
                SELECT report.id, report.deactivated_at, line.depth + 1
                FROM line
                JOIN employees report ON report.manager_id = line.id AND report.tenant_id = :tenant
            ) CYCLE id SET is_cycle USING path
            SELECT id FROM line WHERE NOT is_cycle AND id <> :person AND deactivated_at IS NULL ORDER BY depth, id
            SQL, $employeeId);
    }

    /**
     * @return list<string>
     */
    private function walk(string $sql, string $employeeId): array
    {
        if (! Str::isUuid($employeeId)) {
            return [];
        }

        $rows = DB::select($sql, ['person' => $employeeId, 'tenant' => $this->tenant->id()]);

        return array_values(array_filter(array_column($rows, 'id'), is_string(...)));
    }
}
