<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Data;

use Carbon\CarbonImmutable;

/**
 * A signature of a device key the server verified (plan B of the offline marks): the key and the person it belongs to, and what the
 * pass it came with says. WorkerAccess does not judge the pass against the clock: Attendance does, with the declared time of the
 * mark (the pass expiry, when it was issued and, if the key was revoked after the ticket, `revokedAt`).
 */
final readonly class DeviceSigned
{
    public function __construct(
        public string $deviceKeyId,
        public string $employeeId,
        public string $passId,
        public CarbonImmutable $passIssuedAt,
        public CarbonImmutable $passExpiresAt,
        public ?CarbonImmutable $revokedAt = null,
    ) {}
}
