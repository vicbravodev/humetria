<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Support;

/** A published announcement as the cache keeps it for the panels: its moments as Unix seconds. */
final readonly class LiveAnnouncement
{
    public function __construct(
        public string $id,
        public string $kind,
        public ?string $plan,
        public string $message,
        public int $startsAt,
        public int $endsAt,
    ) {}

    public function runsAt(int $now): bool
    {
        return $this->startsAt <= $now && $now < $this->endsAt;
    }
}
