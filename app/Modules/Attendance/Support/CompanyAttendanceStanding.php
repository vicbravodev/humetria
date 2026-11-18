<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Contracts\AttendanceStanding;
use App\Modules\Attendance\Data\AttendanceSetup;
use App\Modules\Attendance\Enums\CorrectionStatus;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Models\CorrectionRequest;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\WorkCenters;

/** The counts of «Puesta en marcha» and of the queue of corrections, in five queries whatever the size of the company. */
final readonly class CompanyAttendanceStanding implements AttendanceStanding
{
    public function __construct(private WorkCenters $workCenters, private Roster $roster) {}

    public function current(): AttendanceSetup
    {
        $people = PanelScope::of($this->workCenters, $this->roster, null)->people;

        return new AttendanceSetup(
            AttendancePolicy::query()->exists(),
            count($people) - ($people === [] ? 0 : AttendanceSchedule::query()->among('employee_id', $people)->count()),
            CorrectionRequest::query()->where('status', CorrectionStatus::Pending)->count(),
        );
    }
}
