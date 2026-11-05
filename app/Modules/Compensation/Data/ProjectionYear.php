<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Data;

use Carbon\CarbonImmutable;

/**
 * One year of the projection, in cents: what the company pays without the change (`beforeCents`) and with it
 * (`afterCents`), starting at `effectiveOn`. Only the first year has retroactive pay.
 */
final readonly class ProjectionYear
{
    /** @param  list<string>  $staleKeys  fiscal values this year carries past their vigencia */
    public function __construct(
        public int $year,
        public CarbonImmutable $effectiveOn,
        public int $beforeCents,
        public int $afterCents,
        public int $retroactiveCents,
        public int $people,
        public array $staleKeys,
    ) {}

    public function deltaCents(): int
    {
        return $this->afterCents - $this->beforeCents;
    }

    /** What the change costs that year, retroactivity included. */
    public function costCents(): int
    {
        return $this->deltaCents() + $this->retroactiveCents;
    }
}
