<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Data;

/**
 * A new key a person's device created and the server verified: its id and its COSE public key (both base64url) and the
 * counter it started at. Secret-adjacent: never logged.
 */
final readonly class CredentialData
{
    public function __construct(
        public string $credentialId,
        public string $publicKey,
        public int $signCount,
    ) {}
}
