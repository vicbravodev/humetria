<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Data;

use App\Modules\Organization\Data\CompensationPerson;

/** A person who earns something other than their category and level's salary, with the daily salary they really earn. */
final readonly class IndividualSalary
{
    public function __construct(public CompensationPerson $person, public int $dailySalaryCents) {}
}
