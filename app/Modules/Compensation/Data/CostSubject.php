<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Data;

use App\Modules\Compensation\Enums\PayGroup;

/** What the cost of a benefit depends on in one person on one day: their salary, who they are and how long they have served. */
final readonly class CostSubject
{
    public function __construct(
        public int $dailySalaryCents,
        public PayGroup $group,
        public string $jobGradeId,
        public int $tenureYears,
        public int $tenureMonths,
    ) {}
}
