<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Data;

/** The cost of the people who share an area, a category and level and a pay group over the first 12 months, in cents. A null area is "none". */
final readonly class CostCell
{
    public function __construct(public ?string $areaId, public string $jobGradeId, public string $group, public int $people, public int $beforeCents, public int $afterCents) {}
}
