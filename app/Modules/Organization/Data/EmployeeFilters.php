<?php

declare(strict_types=1);

namespace App\Modules\Organization\Data;

/**
 * How the employees list is narrowed: text in the name, email or number, a work center, an area, and active or not.
 */
final readonly class EmployeeFilters
{
    public function __construct(
        public string $search = '',
        public ?string $workCenterId = null,
        public ?string $areaId = null,
        public bool $active = true,
    ) {}
}
