<?php

declare(strict_types=1);

namespace App\Modules\Operations\Contracts;

use Carbon\CarbonImmutable;

/**
 * The scheduler's pulse: `schedule:heartbeat` beats every five minutes (besides its log line), and the console reads when it last did.
 */
interface SchedulerHeartbeat
{
    public function beat(): void;

    /** Null when it never beat, or the cache was emptied since. */
    public function lastBeatAt(): ?CarbonImmutable;
}
