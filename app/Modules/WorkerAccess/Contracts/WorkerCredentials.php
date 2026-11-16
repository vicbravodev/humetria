<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Contracts;

use App\Modules\WorkerAccess\Data\CredentialCounts;
use App\Modules\WorkerAccess\Data\CredentialSummary;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * What the other modules read of the keys of the company's people, and the revocation of one. Always the current company's.
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

    /**
     * Stops a key at once: it no longer verifies. Revoking it again changes nothing.
     *
     * @throws ModelNotFoundException when the key is not the company's
     */
    public function revoke(string $credentialId): void;
}
