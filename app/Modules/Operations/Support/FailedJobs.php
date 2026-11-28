<?php

declare(strict_types=1);

namespace App\Modules\Operations\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The jobs that failed for good (`failed_jobs`, kept 7 days), summed by job: only the job's name leaves the table, never its
 * payload or exception, which can carry a person's data. Retrying is Horizon's, on its own dashboard.
 */
final class FailedJobs
{
    /** At most this many kinds of job: it is a summary, the full list is Horizon's. */
    private const int KINDS = 10;

    /**
     * @return array{today: int, week: list<array{job: string, module: string|null, queue: string, count: int, lastFailedAt: string}>}
     */
    public function handle(CarbonImmutable $startOfToday): array
    {
        $rows = DB::table('failed_jobs')
            ->where('failed_at', '>=', $startOfToday->subDays(7))
            ->groupByRaw("payload::jsonb->>'displayName', queue")
            ->selectRaw("payload::jsonb->>'displayName' as display_name, queue, count(*) as total, max(failed_at) as last_failed_at")
            ->orderByDesc('total')
            ->orderByDesc('last_failed_at')
            ->limit(self::KINDS)
            ->get();

        $week = [];

        foreach ($rows as $row) {
            $class = is_string($row->display_name ?? null) ? $row->display_name : '';
            $week[] = [
                'job' => class_basename($class),
                'module' => Str::startsWith($class, 'App\\Modules\\') ? Str::of($class)->after('App\\Modules\\')->before('\\')->toString() : null,
                'queue' => is_string($row->queue ?? null) ? $row->queue : '',
                'count' => is_int($row->total ?? null) ? $row->total : 0,
                'lastFailedAt' => CarbonImmutable::parse(is_string($row->last_failed_at ?? null) ? $row->last_failed_at : 'now', 'UTC')->toIso8601String(),
            ];
        }

        return ['today' => DB::table('failed_jobs')->where('failed_at', '>=', $startOfToday)->count(), 'week' => $week];
    }
}
