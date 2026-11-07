<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The nightly close, for every company: shortly after local midnight it closes yesterday (CloseDay) for each active person
 * with a mark that day or a working day by their schedule. Once a night is enough: CloseDay stores nothing, so a run
 * again gives the same days.
 */
final readonly class CloseDays
{
    public function __construct(private Companies $companies, private Roster $roster, private CloseDay $closeDay) {}

    /**
     * @return int how many days were closed
     */
    public function handle(): int
    {
        $yesterday = CarbonImmutable::now(config()->string('app.display_timezone'))->subDay()->startOfDay();
        $tenantIds = array_unique([
            ...AttendanceMark::query()->withoutGlobalScopes()->distinct()->get(['tenant_id'])->map(fn (AttendanceMark $mark): string => $mark->tenant_id),
            ...AttendanceSchedule::query()->withoutGlobalScopes()->distinct()->get(['tenant_id'])->map(fn (AttendanceSchedule $schedule): string => $schedule->tenant_id),
        ]);
        $closed = 0;

        foreach ($tenantIds as $tenantId) {
            try {
                // Each in its own company, as the system.
                Context::scope(function () use ($tenantId, $yesterday, &$closed): void {
                    $closed += $this->close($tenantId, $yesterday);
                }, ['user_id' => null]);
            } catch (Throwable $failure) {
                // One company's failure never keeps the others from being closed.
                report($failure);
            }
        }

        Log::info('attendance.days_closed', ['closed' => $closed]);

        return $closed;
    }

    private function close(string $tenantId, CarbonImmutable $day): int
    {
        if ($this->companies->enter($tenantId)->blocked) {
            return 0;
        }

        // Bound in UTC: a Carbon binding loses its offset and the database session is UTC.
        $marked = AttendanceMark::query()->where('marked_at', '>=', $day->utc())->where('marked_at', '<', $day->addDay()->utc())->distinct()->get(['employee_id'])
            ->map(fn (AttendanceMark $mark): string => $mark->employee_id);
        $scheduled = AttendanceSchedule::query()->get(['employee_id', 'minutes_by_weekday'])
            ->filter(fn (AttendanceSchedule $schedule): bool => ($schedule->minutes_by_weekday[$day->isoWeekday()] ?? 0) > 0)
            ->map(fn (AttendanceSchedule $schedule): string => $schedule->employee_id);
        $people = $this->roster->findMany(array_values(array_unique([...$marked, ...$scheduled])));
        $closed = 0;

        foreach ($people as $person) {
            if ($person->active && $this->closeDay->handle($person->id, $day) !== null) {
                $closed++;
            }
        }

        return $closed;
    }
}
