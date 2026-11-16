<?php

declare(strict_types=1);

namespace App\Modules\Demos\Contracts;

/**
 * The door of a live demo, as the room drives it: a session the presenter opens for a while, with a QR the audience scans
 * to get a free demo worker each.
 */
interface DoorControl
{
    /**
     * Opens a session for that long, closing any other; the door's address is returned this once (only its hash is kept).
     *
     * @return array{url: string}
     */
    public function open(int $minutes): array;

    public function close(): void;

    /**
     * Whether a session is open and until when (ISO 8601), and the workers reserved for the audience: taken, free, all.
     *
     * @return array{open: bool, expires_at: ?string, claimed: int, free: int, total: int}
     */
    public function state(): array;
}
