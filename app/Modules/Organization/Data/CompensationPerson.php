<?php

declare(strict_types=1);

namespace App\Modules\Organization\Data;

use App\Modules\Organization\Enums\StaffGroup;
use Carbon\CarbonImmutable;

/**
 * What the contract calculator may know about one person, who is told apart by the company's own employee number: never
 * a name, a contact, a gender or the internal id.
 */
final readonly class CompensationPerson
{
    public function __construct(
        public string $employeeNumber,
        public string $jobGradeId,
        public ?string $areaId,
        public StaffGroup $group,
        public ?string $state,
        public CarbonImmutable $hiredOn,
    ) {}
}
