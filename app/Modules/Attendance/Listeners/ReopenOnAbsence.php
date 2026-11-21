<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Listeners;

use App\Modules\Attendance\Actions\ReopenWeeks;
use App\Modules\Leave\Events\AbsenceChanged;

/** An absence approved or cancelled reopens the answered weeks of its days, inside the change's transaction. */
final readonly class ReopenOnAbsence
{
    public function __construct(private ReopenWeeks $reopen) {}

    public function handle(AbsenceChanged $event): void
    {
        $this->reopen->forAbsence($event->requestId, $event->employeeId, $event->from, $event->to);
    }
}
