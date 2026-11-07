<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Events;

/**
 * RH corrected a mark of a person: `markId` is the correction, `correctsMarkId` the mark it replaces. Dispatched inside the
 * correction's transaction. Ids only: never a time, a position or the reason.
 */
final readonly class MarkCorrected
{
    public function __construct(public string $markId, public string $correctsMarkId, public string $employeeId) {}
}
