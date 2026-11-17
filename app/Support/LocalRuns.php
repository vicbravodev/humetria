<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Exactly once per local day (design of time zones §4, phase 3): a dispatcher that ticks every 15 minutes claims the run of
 * a task for a work center or a company on that one's local date; the database's unique row lets only the first claim
 * through, whatever ticks ran twice, overlapped or were retried.
 */
final class LocalRuns
{
    /** Whether this call took the run; false when it was already taken. */
    public static function claim(string $task, string $scopeId, string $localDate): bool
    {
        // ponytail: one row per center and day, never pruned; a few thousand a year per company. Prune past a year if it grows.
        return DB::table('local_runs')->insertOrIgnore(['task' => $task, 'scope_id' => $scopeId, 'local_date' => $localDate]) === 1;
    }
}
