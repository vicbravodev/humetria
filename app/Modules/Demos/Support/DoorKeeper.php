<?php

declare(strict_types=1);

namespace App\Modules\Demos\Support;

use App\Modules\Demos\Actions\CloseDoor;
use App\Modules\Demos\Actions\OpenDoor;
use App\Modules\Demos\Actions\ReadDoorState;
use App\Modules\Demos\Contracts\DoorControl;
use Closure;
use Illuminate\Support\Facades\Context;
use Spatie\Permission\PermissionRegistrar;

/**
 * The door as the room drives it: its own actions, behind the room's contract. They work inside the demo company, and
 * only for that: the console's request must not go on as the demo, or the room itself would carry the demo's mark.
 */
final readonly class DoorKeeper implements DoorControl
{
    public function __construct(private OpenDoor $open, private CloseDoor $close, private ReadDoorState $state, private PermissionRegistrar $permissions) {}

    public function open(int $minutes): array
    {
        return $this->outside(fn (): array => $this->open->handle($minutes));
    }

    public function close(): void
    {
        $this->outside(function (): bool {
            $this->close->handle();

            return true;
        });
    }

    public function state(): array
    {
        return $this->outside(fn (): array => $this->state->handle()->toArray());
    }

    /**
     * Runs the door's action and leaves the request as it found it. Entering the company also sets Spatie's team, which
     * Context::scope does not restore: it is put back by hand.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $action
     * @return TResult
     */
    private function outside(Closure $action): mixed
    {
        $team = $this->permissions->getPermissionsTeamId();

        try {
            return Context::scope($action);
        } finally {
            $this->permissions->setPermissionsTeamId($team);
        }
    }
}
