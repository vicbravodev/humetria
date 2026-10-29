<?php

declare(strict_types=1);

namespace App\Modules\Organization\Data;

use App\Modules\Organization\Enums\Gender;

/**
 * What a person fills in for an employee. The company never comes from here: it is the current one.
 */
final readonly class EmployeeData
{
    /**
     * @param  string|null  $email  Lowercase.
     * @param  string|null  $mobile  10 digits.
     * @param  string  $area  The area's name: an area the company does not have yet is created.
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
    ) {}
}
