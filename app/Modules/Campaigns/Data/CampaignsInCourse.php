<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Data;

/**
 * The company's campaigns in course, as the frame shows them: how many, and of the one that closes soonest, the days
 * from today to its last one and the percent of its days gone by (0 to 100). Without any, no days and none gone by.
 */
final readonly class CampaignsInCourse
{
    public function __construct(
        public int $count,
        public ?int $daysLeft,
        public int $elapsed,
    ) {}
}
