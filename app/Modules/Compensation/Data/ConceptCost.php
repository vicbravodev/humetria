<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Data;

use App\Modules\Compensation\Enums\CostConcept;

/** One line of the cascade over the first 12 months, in cents. `recurringCents` is the yearly change at the effective date. */
final readonly class ConceptCost
{
    public function __construct(
        public CostConcept $type,
        public string $key,
        public int $beforeCents,
        public int $afterCents,
        public int $recurringCents,
        public int $retroactiveCents,
    ) {}

    public function deltaCents(): int
    {
        return $this->afterCents - $this->beforeCents;
    }

    public function firstYearCents(): int
    {
        return $this->deltaCents() + $this->retroactiveCents;
    }
}
