<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Data;

use Carbon\CarbonImmutable;

/** The daily salary, in cents, of a category and level from a date on. */
final readonly class GradeSalaryData
{
    public function __construct(
        public string $category,
        public string $level,
        public int $dailySalaryCents,
        public CarbonImmutable $effectiveFrom,
    ) {}
}
