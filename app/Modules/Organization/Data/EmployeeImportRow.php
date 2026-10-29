<?php

declare(strict_types=1);

namespace App\Modules\Organization\Data;

/**
 * One valid row of an import: the employee's data and, when it updates someone, who.
 */
final readonly class EmployeeImportRow
{
    public function __construct(
        public EmployeeData $data,
        public ?string $existingId,
    ) {}
}
