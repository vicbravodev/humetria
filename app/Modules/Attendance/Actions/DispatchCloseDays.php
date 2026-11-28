<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Modules\Attendance\Jobs\CloseCenterDays;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\Timezones;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Support\LocalRuns;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Every 15 minutes (routes/console.php): for each work center of each company with a registro whose own clock passed
 * 00:15, claims its close of that local day (LocalRuns) and queues it (CloseCenterDays). A tick missed is made up by the
 * next one that day; one that runs twice queues nothing twice. The server's zone does not matter.
 */
final readonly class DispatchCloseDays
{
    public const string TASK = 'attendance.close-days';

    /** Minutes after the center's midnight: shortly after it, as the nightly close always ran. */
    private const int AFTER_MIDNIGHT = 15; // @pest-mutate-ignore: DecrementInteger,IncrementInteger

    public function __construct(private Companies $companies, private WorkCenters $centers, private Timezones $timezones) {}

    /** @return int how many closes were queued */
    public function handle(): int
    {
        $tenantIds = array_unique([
            ...AttendanceMark::query()->withoutGlobalScopes()->distinct()->get(['tenant_id'])->map(fn (AttendanceMark $mark): string => $mark->tenant_id),
            ...AttendanceSchedule::query()->withoutGlobalScopes()->distinct()->get(['tenant_id'])->map(fn (AttendanceSchedule $schedule): string => $schedule->tenant_id),
        ]);
        $queued = 0;

        foreach ($tenantIds as $tenantId) {
            try {
                Context::scope(function () use ($tenantId, &$queued): void {
                    $this->companies->enter($tenantId);
                    $centers = $this->centers->all();
                    $zones = $this->timezones->forCenters(array_map(fn ($center): string => $center->id, $centers));

                    foreach ($zones as $centerId => $zone) {
                        $now = CarbonImmutable::now($zone);

                        if ($now->hour * 60 + $now->minute >= self::AFTER_MIDNIGHT && LocalRuns::claim(self::TASK, $centerId, $now->toDateString())) {
                            CloseCenterDays::dispatch($tenantId, $centerId, $now->toDateString());
                            $queued++;
                        }
                    }
                }, ['user_id' => null]);
            } catch (Throwable $failure) {
                // One company's failure never keeps the others from being dispatched.
                report($failure);
            }
        }

        if ($queued > 0) {
            Log::info('attendance.days_close_dispatched', ['queued' => $queued]);
        }

        return $queued;
    }
}
