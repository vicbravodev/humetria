<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Contracts;

use App\Modules\WorkerAccess\Data\SignedBy;

/**
 * The signature of a person's key (WebAuthn): any module that asks a person to sign an action gives the browser the options and
 * verifies the answer here. A challenge is random, lives 60 seconds, is tied to the context that asked and is accepted once.
 */
interface WorkerSignatures
{
    /**
     * The options for `navigator.credentials.get()`.
     *
     * @return array<mixed>
     */
    public function options(string $context): array;

    /**
     * Whose key signed, or null for any answer that does not verify (one answer for every cause).
     *
     * @param  array<mixed>  $response  the browser's PublicKeyCredential as JSON
     */
    public function verify(string $context, array $response): ?SignedBy;
}
