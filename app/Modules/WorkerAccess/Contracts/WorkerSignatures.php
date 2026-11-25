<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Contracts;

use App\Modules\WorkerAccess\Data\SignedBy;
use Carbon\CarbonImmutable;

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

    /**
     * Whose key signed `$expected`, a challenge the caller built (the offline mark), or null for any answer that does not
     * verify. Reads only: no cache, no counter, nothing written (not even the key's last use), so verifying the same signature
     * twice gives the same answer; the caller prevents the repetition (the ticket of Attendance). A key revoked after
     * `$ticketIssuedAt` still verifies and `SignedBy::$revokedAt` says when; one revoked at that instant or before does not.
     * It does not check that the person is still active: that is the caller's.
     *
     * @param  string  $expected  the 32 raw bytes of the SHA-256 of the canonical string in UTF-8 (`hash('sha256', $canonical, true)`, never hex)
     * @param  array<mixed>  $response  the browser's PublicKeyCredential as JSON
     */
    public function verifyChallenge(string $expected, array $response, CarbonImmutable $ticketIssuedAt): ?SignedBy;
}
