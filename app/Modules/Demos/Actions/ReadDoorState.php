<?php

declare(strict_types=1);

namespace App\Modules\Demos\Actions;

use App\Modules\Demos\Contracts\DemoDoorPool;
use App\Modules\Demos\Data\DoorState;
use App\Modules\Demos\Models\DoorAssignment;
use App\Modules\Demos\Models\DoorSession;
use App\Modules\Demos\Support\DemoDoor;

/** The door as the console room shows it. A person handed out stays taken until the demo is reset: their portal link
 * never changes, so handing them to someone else would put two strangers on one personal portal. */
final readonly class ReadDoorState
{
    public function __construct(private DemoDoor $door, private DemoDoorPool $pool) {}

    public function handle(): DoorState
    {
        if ($this->door->enter() === null) {
            return new DoorState(false, null, 0, 0, 0);
        }

        $open = DoorSession::query()->whereNull('closed_at')->where('expires_at', '>', now())->latest('expires_at')->first();
        $total = count($this->pool->employeeIds());
        $claimed = DoorAssignment::query()->count();

        return new DoorState($open !== null, $open?->expires_at->toIso8601String(), $claimed, max(0, $total - $claimed), $total);
    }
}
