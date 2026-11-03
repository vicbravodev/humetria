<?php

declare(strict_types=1);

namespace App\Modules\Organization\Data;

use App\Modules\Organization\Enums\Gender;
use App\Modules\Organization\Enums\StaffGroup;
use Carbon\CarbonImmutable;

/**
 * What a person fills in for an employee. The company never comes from here: it is the current one.
 */
final readonly class EmployeeData
{
    /**
     * @param  string|null  $email  Lowercase.
     * @param  string|null  $mobile  10 digits.
     * @param  string  $area  The area's name: an area the company does not have yet is created.
     * @param  string|null  $jobCategory  With the level, a job grade of the pay scale: one the company does not have yet is created.
     * @param  string|null  $state  The code of the state where they work, for a person without a work center.
     */
    public function __construct(
        public string $name,
        public ?string $email,
        public ?string $mobile,
        public Gender $gender,
        public string $workCenterId,
        public string $area,
        public string $jobTitle,
        public ?string $employeeNumber,
        public ?string $jobCategory,
        public ?string $jobLevel,
        public ?CarbonImmutable $hiredOn,
        public ?StaffGroup $staffGroup,
        public ?string $state,
    ) {}
}
