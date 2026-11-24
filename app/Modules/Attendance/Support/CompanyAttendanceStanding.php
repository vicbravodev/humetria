<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Contracts\AttendanceStanding;
use App\Modules\Attendance\Data\AttendanceSetup;
use App\Modules\Attendance\Enums\CorrectionStatus;
use App\Modules\Attendance\Enums\WeekStatus;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Models\CorrectionRequest;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Timezones;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Carbon\CarbonImmutable;

/** The counts of «Puesta en marcha» (who has no schedule in effect today), of the queue of corrections, of the disputed weeks and of the marks made without a connection that need review, in seven queries whatever the size of the company. */
final readonly class CompanyAttendanceStanding implements AttendanceStanding
{
    public function __construct(private WorkCenters $workCenters, private Roster $roster, private Timezones $timezones) {}

    public function current(): AttendanceSetup
    {
        $people = PanelScope::of($this->workCenters, $this->roster, null)->people;

        return new AttendanceSetup(
            AttendancePolicy::query()->exists(),
            count($people) - ($people === [] ? 0 : AttendanceSchedule::query()->among('employee_id', $people)->startedBy(CarbonImmutable::now($this->timezones->forTenant())->toDateString())->distinct()->count('employee_id')),
            CorrectionRequest::query()->where('status', CorrectionStatus::Pending)->count(),
            WeeklyConfirmation::query()->where('status', WeekStatus::Disputed)->count(),
            OfflineMarkPages::needsReviewCount(),
        );
    }
}
