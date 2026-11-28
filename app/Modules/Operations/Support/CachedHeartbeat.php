<?php

declare(strict_types=1);

namespace App\Modules\Operations\Support;

use App\Modules\Operations\Contracts\SchedulerHeartbeat;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/** The last beat in the app's cache (the database in production): shared by the scheduler and the web. */
final class CachedHeartbeat implements SchedulerHeartbeat
{
    private const string KEY = 'operations.scheduler_heartbeat';

    public function beat(): void
    {
        Cache::forever(self::KEY, now()->getTimestamp());
    }

    public function lastBeatAt(): ?CarbonImmutable
    {
        $beat = Cache::get(self::KEY);

        return is_int($beat) ? CarbonImmutable::createFromTimestamp($beat) : null;
    }
}
