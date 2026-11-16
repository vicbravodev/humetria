<?php

declare(strict_types=1);

namespace App\Modules\Demos\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * The door as the console room shows it: whether it is open and until when, and how many of its people are taken and free.
 *
 * @implements Arrayable<string, bool|int|string|null>
 */
final readonly class DoorState implements Arrayable
{
    public function __construct(
        public bool $open,
        public ?string $expiresAt,
        public int $claimed,
        public int $free,
        public int $total,
    ) {}

    /** @return array{open: bool, expires_at: string|null, claimed: int, free: int, total: int} */
    public function toArray(): array
    {
        return ['open' => $this->open, 'expires_at' => $this->expiresAt, 'claimed' => $this->claimed, 'free' => $this->free, 'total' => $this->total];
    }
}
