<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Data;

/** A benefit whose terms a scenario changes: its new value in hundredths (see BenefitKind) and, if it grows with seniority, its new table. */
final readonly class TermChange
{
    /** @param  ?list<array{from_years: int, value: int}>  $tenureTable */
    public function __construct(public string $benefitId, public int $value, public ?array $tenureTable) {}
}
