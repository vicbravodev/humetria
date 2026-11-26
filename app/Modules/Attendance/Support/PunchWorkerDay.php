<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\Timezones;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\WorkerAccess\Contracts\WorkerDays;
use App\Modules\WorkerAccess\Data\WorkerDay;
use App\Modules\WorkerAccess\Data\WorkerIdentity;
use LogicException;

/**
 * The day of the person in their start, for a company with the registro de jornada: their marks (PunchDay, the one place
 * the day is written) with the instant of each, so the start can count the hours as they pass, and what may come next.
 */
final readonly class PunchWorkerDay implements WorkerDays
{
    public function __construct(private Companies $companies, private Roster $roster, private Timezones $timezones, private WorkCenters $centers) {}

    public function of(WorkerIdentity $person): ?WorkerDay
    {
        $entry = $this->companies->current()->allows('attendance') ? $this->roster->find($person->employeeId) : null;

        if ($entry === null) {
            return null;
        }

        $marks = PunchDay::models($entry->id, $this->timezones->forCenter($entry->workCenterId));

        return new WorkerDay(
            $this->centers->findActive($entry->workCenterId)->name ?? '',
            array_values($marks->map(function (AttendanceMark $mark): array {
                $at = $mark->marked_at ?? throw new LogicException('A mark has no time.');

                return ['type' => $mark->type->value, 'time' => $at->setTimezone($mark->timezone)->format('H:i'), 'at' => $at->getTimestampMs()];
            })->all()),
            array_map(fn (MarkType $type): string => $type->value, MarkType::allowedAfter($marks->last()?->type)),
        );
    }
}
