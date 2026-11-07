<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Data;

/**
 * A signature the server verified: the linked device that made it and the person it belongs to.
 */
final readonly class VerifiedAssertion
{
    public function __construct(
        public string $deviceId,
        public string $employeeId,
    ) {}
}
