<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Contracts;

use App\Modules\WorkerAccess\Data\CredentialData;

/**
 * Creating a person's key: the options for the browser, the check of the key it made, and its saving.
 */
interface WorkerKeys
{
    /**
     * The options for `navigator.credentials.create()` of a person's new key.
     *
     * @return array<mixed>
     */
    public function registrationOptions(string $employeeId, string $name): array;

    /**
     * The key a person's device made, or null when it does not verify.
     *
     * @param  array<mixed>  $response  the browser's PublicKeyCredential as JSON
     */
    public function verifyRegistration(string $employeeId, array $response): ?CredentialData;

    /**
     * Saves a verified key of the person, to the audit trail and the log. Its id, or null when that key is already saved.
     */
    public function save(string $employeeId, CredentialData $key, string $label): ?string;
}
