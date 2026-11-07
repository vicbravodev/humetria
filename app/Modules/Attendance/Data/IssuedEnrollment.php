<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Data;

/**
 * A new enrollment link. Its URL is emailed to the person and never handed to RH, so RH cannot link a device as them;
 * `manualUrl` carries it only when the person has no email, for RH to hand over (and that is in the audit trail).
 */
final readonly class IssuedEnrollment
{
    public function __construct(
        public string $linkId,
        public ?string $manualUrl,
    ) {}
}
