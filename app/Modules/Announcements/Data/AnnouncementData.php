<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Data;

use App\Modules\Announcements\Enums\AnnouncementAudience;
use App\Modules\Announcements\Enums\AnnouncementKind;
use Carbon\CarbonImmutable;

/** An announcement as the console writes it, its moments already in UTC. */
final readonly class AnnouncementData
{
    public function __construct(
        public AnnouncementKind $kind,
        public AnnouncementAudience $audience,
        public ?string $plan,
        public string $title,
        public string $message,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public bool $publish,
    ) {}
}
