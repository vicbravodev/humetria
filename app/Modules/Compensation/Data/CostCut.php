<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Data;

/** The cost of one slice of the roster (an area, a category and level, a group) over the first 12 months, in cents. A null key is "none". */
final readonly class CostCut
{
    public function __construct(public ?string $key, public int $people, public int $beforeCents, public int $afterCents) {}

    public function deltaCents(): int
    {
        return $this->afterCents - $this->beforeCents;
    }
}
