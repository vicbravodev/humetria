<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Events;

/**
 * RH resolved a person's request to correct their record, approving or rejecting it. Dispatched after the commit. Ids only:
 * never the reason, the note or a time.
 */
final readonly class CorrectionResolved
{
    public function __construct(public string $requestId, public string $employeeId, public bool $approved) {}
}
