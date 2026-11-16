<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Contracts;

use App\Modules\WorkerAccess\Data\CredentialCounts;
use App\Modules\WorkerAccess\Data\CredentialSummary;

/**
 * What the other modules read of the keys of the company's people. Always the current company's.
 */
interface WorkerCredentials
{
    /**
     * The keys in use and the revoked ones of each person; a person with none is left out. One query.
     *
     * @param  list<string>  $employeeIds
     * @return array<string, CredentialCounts>
     */
    public function countsByEmployee(array $employeeIds): array;

    /**
     * The keys of each person, the active first and then the newest; a person with none is left out. One query.
     *
     * @param  list<string>  $employeeIds
     * @return array<string, list<CredentialSummary>>
     */
    public function ofEmployees(array $employeeIds): array;
}
