<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Data;

/**
 * A new enrollment link. Its URL is emailed to the person and never handed to RH, so RH cannot link a device as them;
 * `manualUrl` carries it only when the person has no email, for RH to hand over (and that is in the audit trail). The
 * person's name is for RH's message.
 */
final readonly class IssuedEnrollment
{
    public function __construct(
        public string $linkId,
        public ?string $manualUrl,
        public string $personName,
        /** The name of the person's center when nobody can mark in it yet; null when it can. */
        public ?string $centerNotReady = null,
    ) {}
}
