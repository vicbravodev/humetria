<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Data;

use Carbon\CarbonImmutable;

/** The terms of a benefit from a date on: a value in hundredths (see BenefitKind) and, if it grows with seniority, its table. */
final readonly class TermData
{
    /**
     * @param  ?list<array{from_years: int, value: int}>  $tenureTable
     */
    public function __construct(
        public CarbonImmutable $effectiveFrom,
        public int $value,
        public ?array $tenureTable = null,
    ) {}
}
