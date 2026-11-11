<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Pulse;

use App\Modules\Engagement\Enums\PulseFrequency;
use App\Modules\Engagement\Enums\PulseMode;
use App\Modules\Engagement\Support\Bank\PulseComposer;

/** What the next scheduled pulse of the company will be: the mode RH chose, or the calendar's, and its questions. */
final readonly class UpcomingPulse
{
    /**
     * @param  list<string>  $refs
     */
    public function __construct(public PulseMode $mode, public array $refs) {}

    public static function resolve(PulseFrequency $frequency, ?PulseMode $chosen): self
    {
        $prioritized = PriorityDrivers::ofLatestClimate();
        $mode = $chosen ?? PulsePlanner::modeFor($frequency, PulseHistory::launched(), $prioritized !== []);

        return new self($mode, PulseComposer::compose($mode, $prioritized, PulseHistory::tours(), PulseHistory::hasPreviousWave()));
    }
}
