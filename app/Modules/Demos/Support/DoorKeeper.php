<?php

declare(strict_types=1);

namespace App\Modules\Demos\Support;

use App\Modules\Demos\Actions\CloseDoor;
use App\Modules\Demos\Actions\OpenDoor;
use App\Modules\Demos\Actions\ReadDoorState;
use App\Modules\Demos\Contracts\DoorControl;

/** The door as the room drives it: its own actions, behind the room's contract. */
final readonly class DoorKeeper implements DoorControl
{
    public function __construct(private OpenDoor $open, private CloseDoor $close, private ReadDoorState $state) {}

    public function open(int $minutes): array
    {
        return $this->open->handle($minutes);
    }

    public function close(): void
    {
        $this->close->handle();
    }

    public function state(): array
    {
        return $this->state->handle()->toArray();
    }
}
