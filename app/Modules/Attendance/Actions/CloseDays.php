<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Attendance\Support\EffectiveMarks;
use App\Modules\Attendance\Support\WeekPeriod;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\Timezones;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The nightly close of a work center, in its own zone (design of time zones, §3): shortly after its local midnight it closes
 * its day before yesterday (CloseDay) for each active person of the center with a mark that day or a working day by their
 * schedule — a whole day after it ended, so a night shift that started on it has had time to mark its end and is not
 * flagged as missing one — and leaves pending the week that last ended, in that zone, of each active person of the center
 * with marks in it (silence is not conformity: nobody confirms it for them). Once a night is enough: CloseDay stores nothing
 * and a week is opened once, so a run again changes nothing; and every night opens the last week again if needed, so a
 * Monday that failed is made up the next night.
 */
final readonly class CloseDays
{
    public function __construct(private Companies $companies, private Roster $roster, private CloseDay $closeDay, private WorkCenters $centers, private Timezones $timezones) {}

    /**
     * Every center of every company with attendance, each at its own today: what the demo prepares, and a catch-up.
     *
     * @param  string|null  $tenantId  only this company (the demo, prepared on its own); null for every company
     * @return int how many days were closed
     */
    public function handle(?string $tenantId = null): int
    {
        $tenantIds = $tenantId !== null ? [$tenantId] : array_unique([
            ...AttendanceMark::query()->withoutGlobalScopes()->distinct()->get(['tenant_id'])->map(fn (AttendanceMark $mark): string => $mark->tenant_id),
            ...AttendanceSchedule::query()->withoutGlobalScopes()->distinct()->get(['tenant_id'])->map(fn (AttendanceSchedule $schedule): string => $schedule->tenant_id),
        ]);
        $closed = 0;

        foreach ($tenantIds as $id) {
            try {
                // Each in its own company, as the system.
                Context::scope(function () use ($id, &$closed): void {
                    $this->companies->enter($id);
                    foreach ($this->centers->all() as $center) {
                        $closed += $this->center($id, $center->id);
                    }
                }, ['user_id' => null]);
            } catch (Throwable $failure) {
                // One company's failure never keeps the others from being closed.
                report($failure);
            }
        }

        Log::info('attendance.days_closed', ['closed' => $closed]);

        return $closed;
    }

    /**
     * One work center's night, at its own today; the company is entered here, so a job of the dispatcher can call it alone.
     *
     * @return int how many days were closed
     */
    public function center(string $tenantId, string $workCenterId): int
    {
        if ($this->companies->enter($tenantId)->stopped('attendance')) {
            return 0;
        }

        $timezone = $this->timezones->forCenter($workCenterId);
        $day = CarbonImmutable::now($timezone)->subDays(2)->startOfDay();
        // Bound in UTC: a Carbon binding loses its offset and the database session is UTC.
        $marked = AttendanceMark::query()->where('marked_at', '>=', $day->utc())->where('marked_at', '<', $day->addDay()->utc())->distinct()->get(['employee_id'])
            ->map(fn (AttendanceMark $mark): string => $mark->employee_id);
        $scheduled = AttendanceSchedule::query()->get(['employee_id', 'minutes_by_weekday'])
            ->filter(fn (AttendanceSchedule $schedule): bool => ($schedule->minutes_by_weekday[$day->isoWeekday()] ?? 0) > 0)
            ->map(fn (AttendanceSchedule $schedule): string => $schedule->employee_id);
        $closed = 0;

        foreach ($this->peopleOf($workCenterId, [...$marked, ...$scheduled]) as $person) {
            if ($this->closeDay->handle($person->id, $day, $timezone) !== null) {
                $closed++;
            }
        }

        $this->openWeek($tenantId, $workCenterId, WeekPeriod::containing(CarbonImmutable::now()->subWeek(), $timezone));

        return $closed;
    }

    private function openWeek(string $tenantId, string $workCenterId, WeekPeriod $week): void
    {
        // Whoever marked in the week; their own effective marks decide (a night that ends on Monday belongs to Sunday).
        $marked = AttendanceMark::query()->where('marked_at', '>=', $week->startUtc())->where('marked_at', '<', $week->nextStartUtc())->distinct()->get(['employee_id'])
            ->map(fn (AttendanceMark $mark): string => $mark->employee_id);

        foreach ($this->peopleOf($workCenterId, $marked->all()) as $person) {
            if (EffectiveMarks::forWeek($person->id, $week) === []) {
                continue;
            }

            $confirmation = WeeklyConfirmation::query()->createOrFirst(['employee_id' => $person->id, 'week_start' => $week->start()->toDateString()], ['tenant_id' => $tenantId]);

            if ($confirmation->wasRecentlyCreated) {
                Log::info('attendance.week_opened', ['confirmation_id' => $confirmation->id, 'employee_id' => $person->id, 'week_start' => $week->start()->toDateString()]);
            }
        }
    }

    /**
     * The active people of the center among `$employeeIds`: a person is closed by the center they belong to today.
     *
     * @param  array<array-key, string>  $employeeIds
     * @return list<RosterEntry>
     */
    private function peopleOf(string $workCenterId, array $employeeIds): array
    {
        return array_values(array_filter(
            $this->roster->findMany(array_values(array_unique($employeeIds))),
            fn (RosterEntry $person): bool => $person->active && $person->workCenterId === $workCenterId,
        ));
    }
}
