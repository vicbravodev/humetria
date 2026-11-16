<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\CorrectionStatus;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Enums\ShiftType;
use App\Modules\Attendance\Enums\WeekStatus;
use App\Modules\Attendance\Models\AgreementAcceptance;
use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Models\AttendanceZone;
use App\Modules\Attendance\Models\CorrectionRequest;
use App\Modules\Attendance\Models\EnrollmentLink;
use App\Modules\Attendance\Models\KioskDevice;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Attendance\Support\EffectiveMarks;
use App\Modules\Attendance\Support\WeekPeriod;
use App\Modules\Attendance\Support\WorkedDay;
use App\Modules\Demos\Contracts\DemoWorkspace;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\DemoClock;
use Database\Seeders\Demo\DemoPerson;
use Database\Seeders\Demo\DemoRoster;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    // In production the jobs run later on the queue: the story must stand without them.
    Bus::fake();
});

/**
 * The employee ids of a plant area.
 *
 * @return list<string>
 */
function demoPlantArea(string $area): array
{
    return array_values(array_map(
        fn (DemoPerson $person): string => DemoRoster::employeeId($person->key),
        array_filter(DemoRoster::people(), fn (DemoPerson $person): bool => $person->centerKey === 'plant' && $person->area === $area),
    ));
}

/** The minutes worked above the schedule in a week by a person, as the panel counts them (criterio 2.9). */
function demoOvertime(string $employeeId, WeekPeriod $week): int
{
    $schedule = AttendanceSchedule::query()->where('employee_id', $employeeId)->first();
    $overtime = 0;

    foreach (EffectiveMarks::byDay($employeeId, $week) as $date => $marks) {
        $day = WorkedDay::hours(CarbonImmutable::parse($date, DemoClock::TIMEZONE), $marks, $schedule);
        $overtime += max(0, $day->workedMinutes - $day->scheduledMinutes);
    }

    return $overtime;
}

it('tells the attendance story: months of marks, the night shift overtime, a kiosk and nothing to impersonate a person', function (): void {
    resolve(DemoWorkspace::class)->prepare();

    $tenantId = resolve(DemoCompanies::class)->id();
    $centers = WorkCenter::query()->pluck('id', 'name');
    $today = CarbonImmutable::now(DemoClock::TIMEZONE)->startOfDay();
    $lastWeek = WeekPeriod::containing($today->subWeek());
    $nightShift = demoPlantArea('Producción turno 2');
    $dayShift = demoPlantArea('Producción turno 1');

    // zones, schedules and a policy for every center
    expect(AttendanceZone::query()->distinct()->pluck('work_center_id')->sort()->values()->all())->toBe($centers->sort()->values()->all())
        ->and(AttendanceSchedule::query()->count())->toBe(180)
        ->and(AttendanceSchedule::query()->whereIn('employee_id', $nightShift)->pluck('shift_type')->unique()->all())->toBe([ShiftType::Night])
        ->and(AttendanceSchedule::query()->whereNotIn('employee_id', $nightShift)->pluck('shift_type')->unique()->all())->toBe([ShiftType::Day])
        ->and(AttendancePolicy::query()->count())->toBe(1);

    foreach (AttendanceSchedule::query()->get() as $schedule) {
        // Within the 46 h a week of 2027, so no ordinary hour of the demo turns into overtime when the year changes.
        expect(array_sum($schedule->minutes_by_weekday))->toBeLessThanOrEqual(46 * 60)
            ->and($schedule->minutes_by_weekday[7])->toBe(0);
    }

    // six months of closed days, more overtime at the plant night shift
    $first = AttendanceMark::query()->min('marked_at');

    expect(CarbonImmutable::parse(is_string($first) ? $first : 'now')->lessThan($today->subWeeks(25)))->toBeTrue()
        ->and(AttendanceMark::query()->where('marked_at', '>=', $today->utc())->count())->toBe(0)
        ->and(AttendanceMark::query()->whereNotNull('corrects_mark_id')->count())->toBe(0);

    // Every day ended with its end mark but one (the end someone forgot, now asked for): the night ends the next morning.
    $open = 0;
    foreach (AttendanceMark::query()->distinct()->pluck('employee_id') as $employeeId) {
        foreach (range(0, 26) as $weeks) {
            foreach (EffectiveMarks::byDay($employeeId, WeekPeriod::containing($today->subWeeks($weeks))) as $marks) {
                $open += WorkedDay::missingEnd($marks) ? 1 : 0;
            }
        }
    }

    expect($open)->toBe(1);

    // Last week, everyone on the night shift worked overtime and the morning shift none.
    $night = array_map(fn (string $id): int => demoOvertime($id, $lastWeek), $nightShift);
    $day = array_map(fn (string $id): int => demoOvertime($id, $lastWeek), $dayShift);

    expect(min($night))->toBeGreaterThan(0)
        ->and(array_sum($day))->toBe(0);

    // Further back, before HR acted on the evaluation, the night shift worked even more overtime.
    $historyNight = array_values(array_filter($nightShift, fn (string $id): bool => AttendanceMark::query()->where('employee_id', $id)->where('marked_at', '<', $today->subWeeks(20)->utc())->exists()));
    $historyDay = array_values(array_filter($dayShift, fn (string $id): bool => AttendanceMark::query()->where('employee_id', $id)->where('marked_at', '<', $today->subWeeks(20)->utc())->exists()));

    expect($historyNight)->not->toBeEmpty()
        ->and($historyDay)->not->toBeEmpty()
        ->and(demoOvertime($historyNight[0], WeekPeriod::containing($today->subWeeks(20))))->toBeGreaterThan(demoOvertime($historyNight[0], $lastWeek))
        ->and(demoOvertime($historyDay[0], WeekPeriod::containing($today->subWeeks(20))))->toBe(0);

    // an activated kiosk in the Oficinas center
    $kiosk = KioskDevice::query()->sole();

    expect($kiosk->work_center_id)->toBe($centers[DemoRoster::CENTERS['offices']])
        ->and($kiosk->activated_at)->not->toBeNull()
        ->and($kiosk->revoked_at)->toBeNull();

    // no enrolled devices and no passkeys: whoever marks live links their own phone
    expect(AttendanceDevice::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(EnrollmentLink::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(AgreementAcceptance::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(DB::table('passkeys')->count())->toBe(0);

    // pending correction requests and a week awaiting confirmation
    $requests = CorrectionRequest::query()->get();
    $door = array_map(fn (DemoPerson $person): string => DemoRoster::employeeId($person->key), array_filter(DemoRoster::people(), fn (DemoPerson $person): bool => $person->reservedForDoor));
    $marked = AttendanceMark::query()->where('marked_at', '>=', $lastWeek->startUtc())->where('marked_at', '<', $lastWeek->nextStartUtc())->distinct()->pluck('employee_id');
    $weeks = WeeklyConfirmation::query()->get();

    expect($requests->count())->toBeGreaterThanOrEqual(2)
        ->and($requests->pluck('status')->unique()->all())->toBe([CorrectionStatus::Pending])
        ->and($requests->whereNull('mark_id')->pluck('type')->all())->toBe([MarkType::End])
        ->and($requests->whereNotNull('mark_id')->count())->toBeGreaterThanOrEqual(1)
        ->and($requests->pluck('employee_id')->intersect($door)->all())->toBe([])
        ->and($weeks->pluck('status')->unique()->all())->toBe([WeekStatus::Pending])
        ->and($weeks->map(fn (WeeklyConfirmation $week): string => $week->week_start->toDateString())->unique()->values()->all())->toBe([$lastWeek->start()->toDateString()])
        ->and($weeks->pluck('employee_id')->sort()->values()->all())->toBe($marked->sort()->values()->all());

    // nothing outside the demo company
    foreach ([AttendanceZone::class, AttendanceSchedule::class, AttendancePolicy::class, AttendanceMark::class, KioskDevice::class, CorrectionRequest::class, WeeklyConfirmation::class] as $model) {
        expect($model::query()->withoutGlobalScopes()->where('tenant_id', '!=', $tenantId)->count())->toBe(0);
    }
});
