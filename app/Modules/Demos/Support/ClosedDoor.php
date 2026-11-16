<?php

declare(strict_types=1);

namespace App\Modules\Demos\Support;

use App\Modules\Demos\Contracts\DoorControl;
use LogicException;

/**
 * The door until it is built (its own task binds OpenDoor, CloseDoor and its state in its place): always closed, with no
 * one reserved, and it cannot open.
 */
final readonly class ClosedDoor implements DoorControl
{
    public function open(int $minutes): array
    {
        throw new LogicException('The door is not built yet.');
    }

    public function close(): void {}

    public function state(): array
    {
        return ['open' => false, 'expires_at' => null, 'claimed' => 0, 'free' => 0, 'total' => 0];
    }
}
