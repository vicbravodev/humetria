<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Contracts;

use App\Modules\Compensation\Data\ContractStanding;

/**
 * Where the current company stands with its collective contract, as the start of Compensación shows it: counts only,
 * never a salary, an amount or a person.
 */
interface CompensationStanding
{
    public function current(): ContractStanding;
}
