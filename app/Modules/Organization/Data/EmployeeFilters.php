<?php

declare(strict_types=1);

namespace App\Modules\Organization\Data;

/**
 * How the employees list is narrowed: text in the name, email or number, a work center, an area, active or not, and
 * only those without email (no survey link reaches them).
 */
final readonly class EmployeeFilters
{
    public function __construct(
        public string $search = '',
        public ?string $workCenterId = null,
        public ?string $areaId = null,
        public bool $active = true,
        public bool $withoutEmail = false,
    ) {}
}
