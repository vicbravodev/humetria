<?php

declare(strict_types=1);

namespace App\Modules\Organization\Data;

/**
 * One valid row of an import: the employee's data and, when it updates someone, who. `absent` names the pay scale
 * columns the file does not have (and, in a union's, name, area_id and job_title), which an update leaves as they are.
 */
final readonly class EmployeeImportRow
{
    /**
     * @param  list<string>  $absent  of job_grade_id, hired_on, staff_group and state; in a union's file also name, area_id and job_title.
     */
    public function __construct(
        public EmployeeData $data,
        public ?string $existingId,
        public array $absent,
    ) {}
}
