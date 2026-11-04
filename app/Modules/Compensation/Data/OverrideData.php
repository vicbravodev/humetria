<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Data;

use Carbon\CarbonImmutable;

/** What a person really earns per day, in cents, from a date on; the person is told apart by the company's employee number. */
final readonly class OverrideData
{
    public function __construct(
        public string $employeeNumber,
        public int $dailySalaryCents,
        public CarbonImmutable $effectiveFrom,
        public ?string $note = null,
    ) {}
}
