<?php

declare(strict_types=1);

namespace App\Modules\Organization\Data;

/**
 * How the org chart is narrowed: a work center, an area, or both.
 */
final readonly class OrgFilters
{
    public function __construct(
        public ?string $workCenterId = null,
        public ?string $areaId = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->workCenterId === null && $this->areaId === null;
    }
}
