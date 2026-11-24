<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Data;

use Carbon\CarbonImmutable;

/**
 * A signature the server verified: the person's key that made it and the person it belongs to. `revokedAt` is when the key was
 * revoked, if it was revoked after the ticket of an offline mark was issued (only `verifyChallenge` fills it; the online
 * `verify` never does).
 */
final readonly class SignedBy
{
    public function __construct(
        public string $credentialId,
        public string $employeeId,
        public ?CarbonImmutable $revokedAt = null,
    ) {}
}
