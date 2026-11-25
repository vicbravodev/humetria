<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Events;

/**
 * A mark made without a connection was delivered and accepted: `markId` is the mark it created, `offlineMarkId` the delivery
 * that says how. Dispatched inside the transaction of the delivery, with the person's lock on their marks already taken (as
 * MarkCorrected is). Ids only: never a time, a position or the note.
 */
final readonly class OfflineMarkAccepted
{
    public function __construct(public string $markId, public string $employeeId, public string $offlineMarkId) {}
}
