<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Events;

/** A person disputed their week (`weekStart`, its Monday, Y-m-d) with a verified signature. Ids only: never a reason or an hour. */
final readonly class WeekDisputed
{
    public function __construct(public string $confirmationId, public string $employeeId, public string $weekStart) {}
}
