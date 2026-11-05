<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Data;

use App\Modules\Compensation\Enums\PayGroup;

/** What each person of a category and level and a group receives more a month with the proposal, from the tabulator salary. */
final readonly class GradeRaise
{
    public function __construct(
        public string $jobGradeId,
        public PayGroup $group,
        public int $people,
        public int $dailySalaryCents,
        public int $monthlyRaiseCents,
    ) {}
}
