<?php

declare(strict_types=1);

namespace App\Modules\Organization\Support;

use App\Modules\Organization\Enums\SpanFlag;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;

/**
 * The shape of the company in three numbers: how many levels it has, how many people a manager leads (the median)
 * and how many managers are at the extremes. Among active people only, as the tree shows them. Two queries, whatever
 * the size of the company.
 */
final readonly class OrgIndicators
{
    /** From whoever has no active manager down through active people; it stops on a cycle already in the data. */
    private const string LEVELS = <<<'SQL'
        WITH RECURSIVE walk AS (
            SELECT e.id, 1 AS depth
            FROM employees e
            LEFT JOIN employees m ON m.id = e.manager_id AND m.tenant_id = e.tenant_id
            WHERE e.tenant_id = :tenant AND e.deactivated_at IS NULL AND (m.id IS NULL OR m.deactivated_at IS NOT NULL)
            UNION ALL
            SELECT e.id, w.depth + 1 FROM walk w
            JOIN employees e ON e.manager_id = w.id AND e.tenant_id = :tenant AND e.deactivated_at IS NULL
        ) CYCLE id SET is_cycle USING path
        SELECT COALESCE(max(depth), 0) FROM walk WHERE NOT is_cycle
        SQL;

    /** The active direct reports of each active manager. */
    private const string SPANS = <<<'SQL'
        SELECT count(*) AS reports
        FROM employees r
        JOIN employees m ON m.id = r.manager_id AND m.tenant_id = r.tenant_id
        WHERE r.tenant_id = :tenant AND r.deactivated_at IS NULL AND m.deactivated_at IS NULL
        GROUP BY m.id
        SQL;

    public function __construct(private CurrentTenant $tenant) {}

    /**
     * @return array{levels: int, median_direct_reports: float|null, managers: int, to_review: int}
     */
    public function handle(): array
    {
        $tenant = ['tenant' => $this->tenant->id()];
        $levels = DB::scalar(self::LEVELS, $tenant);
        $spans = array_values(array_filter(array_column(DB::select(self::SPANS, $tenant), 'reports'), is_int(...)));

        return [
            'levels' => is_int($levels) ? $levels : 0,
            'median_direct_reports' => SpanOfControl::median($spans),
            'managers' => count($spans),
            'to_review' => count(array_filter($spans, fn (int $reports): bool => SpanOfControl::flag($reports) instanceof SpanFlag)),
        ];
    }
}
