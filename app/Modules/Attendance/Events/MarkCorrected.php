<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Events;

/**
 * RH corrected a person's record: `markId` is the correction, `correctsMarkId` the mark it replaces (null: a mark the
 * person forgot, added). Dispatched inside the correction's transaction. Ids only: never a time, a position or the reason.
 */
final readonly class MarkCorrected
{
    public function __construct(public string $markId, public ?string $correctsMarkId, public string $employeeId) {}
}
