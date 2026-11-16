<?php

declare(strict_types=1);

use App\Modules\Attendance\Actions\RecordMark;
use App\Modules\Attendance\Data\MarkContext;
use App\Modules\Attendance\Enums\CorrectionStatus;
use App\Modules\Attendance\Enums\MarkMethod;
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
use App\Modules\Leave\Support\StatutoryHolidays;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\DemoClock;
use Database\Seeders\Demo\DemoPerson;
use Database\Seeders\Demo\DemoRoster;
use Database\Seeders\Demo\Stories\LeaveStory;
use Illuminate\Support\Facades\DB;

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

/*
 * The attendance story's groups: DemoStoriesTest includes this file inside its one test, on the prepared demo, so `$this` is
 * that test.
 */
return [
    'has nobody marking on a holiday of art. 74 or of the company' => function (): void {
        $today = CarbonImmutable::now(DemoClock::TIMEZONE)->startOfDay();
        $holidays = [];
        foreach (range($today->year - 1, $today->year) as $year) {
            array_push($holidays, ...StatutoryHolidays::for($year), ...array_map(fn (string $day): string => "{$year}-{$day}", array_keys(LeaveStory::HOLIDAYS)));
        }

        $days = AttendanceMark::query()->toBase()->selectRaw("distinct ((marked_at at time zone 'America/Mexico_City')::date)::text as day")
            ->where('type', MarkType::Start)->pluck('day')->all();

        expect(array_values(array_intersect($days, $holidays)))->toBe([]);
    },

    'keeps the night shift\'s overtime within the norm in the weeks after HR acted: three days and nine hours at most' => function (): void {
        $today = CarbonImmutable::now(DemoClock::TIMEZONE)->startOfDay();

        // The whole weeks since the overtime came down (three weeks ago), up to last week.
        for ($week = WeekPeriod::containing($today->subWeek()); $week->start()->greaterThanOrEqualTo($today->subDays(21)); $week = WeekPeriod::containing($week->start()->subWeek())) {
            foreach (demoPlantArea('Producción turno 2') as $employeeId) {
                $schedule = AttendanceSchedule::query()->where('employee_id', $employeeId)->firstOrFail();
                $days = 0;
                $minutes = 0;

                foreach (EffectiveMarks::byDay($employeeId, $week) as $date => $marks) {
                    $day = WorkedDay::hours(CarbonImmutable::parse($date, DemoClock::TIMEZONE), $marks, $schedule);
                    $extra = max(0, $day->workedMinutes - $day->scheduledMinutes);
                    $days += $extra > 0 ? 1 : 0;
                    $minutes += $extra;
                }

                expect($days)->toBeLessThanOrEqual(3, "{$employeeId} worked overtime on {$days} days of the week of {$week->start()->toDateString()}")
                    ->and($minutes)->toBeLessThanOrEqual(9 * 60);
            }
        }
    },

    'tells the attendance story: months of marks, the night shift overtime, a kiosk and nothing to impersonate a person' => function (): void {
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

        // Every person's marks follow each other as RecordMark lets them, and every day ended with its end mark but one (the
        // end someone forgot, now asked for). Walked once over every mark: no correction exists, so all of them are effective.
        $open = 0;
        $broken = 0;
        $last = [];
        foreach (AttendanceMark::query()->orderBy('employee_id')->orderBy('marked_at')->toBase()->get(['employee_id', 'type']) as $row) {
            $type = MarkType::from(is_string($row->type) ? $row->type : '');
            $employeeId = is_string($row->employee_id) ? $row->employee_id : '';
            $broken += in_array($type, MarkType::allowedAfter($last[$employeeId] ?? null), true) ? 0 : 1;
            $last[$employeeId] = $type;
        }
        foreach ($last as $type) {
            $open += $type === MarkType::Start ? 1 : 0;
        }

        expect($broken)->toBe(0)
            ->and($open)->toBe(1);

        // Coverage: the plant's two shifts reach back six months, everyone has the last four weeks, and the current week has
        // its days up to yesterday.
        $since = AttendanceMark::query()->groupBy('employee_id')->selectRaw('employee_id, min(marked_at) as first, max(marked_at) as last')->toBase()->get()
            ->keyBy('employee_id');

        expect($since)->toHaveCount(180);

        foreach ($since as $employeeId => $span) {
            $first = CarbonImmutable::parse(is_string($span->first) ? $span->first : 'now');
            $latest = CarbonImmutable::parse(is_string($span->last) ? $span->last : 'now');
            $shift = in_array($employeeId, [...$nightShift, ...$dayShift], true);

            expect($latest->greaterThanOrEqualTo($today->subWeek()))->toBeTrue()
                ->and($first->lessThan($today->subWeeks($shift ? 25 : 3)))->toBeTrue();
        }

        // Yesterday back to this week's Monday, or to the last working day before it: a Sunday or a holiday nobody works.
        $holidays = [...StatutoryHolidays::for($today->year), ...StatutoryHolidays::for($today->year - 1)];
        $checked = 0;
        for ($date = $today->subDay(); $date->greaterThanOrEqualTo($today->startOfWeek()) || $checked === 0; $date = $date->subDay()) {
            if ($date->isoWeekday() === 7 || in_array($date->toDateString(), $holidays, true) || isset(LeaveStory::HOLIDAYS[$date->format('m-d')])) {
                continue;
            }

            expect(AttendanceMark::query()->where('marked_at', '>=', $date->utc())->where('marked_at', '<', $date->addDay()->utc())->exists())->toBeTrue();
            $checked++;
        }

        // Last week, everyone on the night shift worked overtime and the morning shift none.
        $night = array_map(fn (string $id): int => demoOvertime($id, $lastWeek), $nightShift);
        $day = array_map(fn (string $id): int => demoOvertime($id, $lastWeek), $dayShift);

        expect(min($night))->toBeGreaterThan(0)
            ->and(array_sum($day))->toBe(0);

        // Further back, before HR acted on the evaluation, the night shift as a whole worked even more overtime; the morning
        // shift still none.
        $before = WeekPeriod::containing($today->subWeeks(20));

        expect(array_sum(array_map(fn (string $id): int => demoOvertime($id, $before), $nightShift)))->toBeGreaterThan(array_sum($night))
            ->and(array_sum(array_map(fn (string $id): int => demoOvertime($id, $before), $dayShift)))->toBe(0);

        // an activated kiosk in the Oficinas center
        $kiosk = KioskDevice::query()->sole();

        expect($kiosk->work_center_id)->toBe($centers[DemoRoster::CENTERS['offices']])
            ->and($kiosk->activated_at)->not->toBeNull()
            ->and($kiosk->revoked_at)->toBeNull();

        // Oficinas marks with the kiosk's QR: inside the zone, or with no location.
        $offices = AttendanceMark::query()->where('work_center_id', $centers[DemoRoster::CENTERS['offices']]);

        expect((clone $offices)->where('method', MarkMethod::Qr)->exists())->toBeTrue()
            ->and((clone $offices)->where('method', MarkMethod::QrAndGeofence)->exists())->toBeTrue()
            ->and((clone $offices)->whereNotIn('method', [MarkMethod::Qr, MarkMethod::QrAndGeofence])->exists())->toBeFalse()
            ->and((clone $offices)->where('marked_at', '<', $kiosk->activated_at)->exists())->toBeFalse();

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
            ->and(AttendanceMark::query()->whereIn('employee_id', $door)->where('marked_at', '>=', $today->utc())->exists())->toBeFalse()
            ->and($weeks->pluck('status')->unique()->all())->toBe([WeekStatus::Pending])
            ->and($weeks->map(fn (WeeklyConfirmation $week): string => $week->week_start->toDateString())->unique()->values()->all())->toBe([$lastWeek->start()->toDateString()])
            ->and($weeks->pluck('employee_id')->sort()->values()->all())->toBe($marked->sort()->values()->all());

        // nothing outside the demo company
        foreach ([AttendanceZone::class, AttendanceSchedule::class, AttendancePolicy::class, AttendanceMark::class, KioskDevice::class, CorrectionRequest::class, WeeklyConfirmation::class] as $model) {
            expect($model::query()->withoutGlobalScopes()->where('tenant_id', '!=', $tenantId)->count())->toBe(0);
        }

        // A mark of the history has the shape of one RecordMark writes: the same columns filled, created when marked.
        $person = array_values(array_filter(DemoRoster::people(), fn (DemoPerson $person): bool => $person->centerKey === 'monterrey' && ! $person->reservedForDoor))[1];
        $employeeId = DemoRoster::employeeId($person->key);
        $bulk = AttendanceMark::query()->where('employee_id', $employeeId)->where('method', MarkMethod::Geofence)->firstOrFail();
        $recorded = resolve(RecordMark::class)->handle($employeeId, MarkType::Start, new MarkContext(
            MarkMethod::Geofence, $bulk->zone_id, (float) $bulk->latitude, (float) $bulk->longitude, $bulk->accuracy_m, $bulk->distance_m, null,
        ))->fresh() ?? throw new RuntimeException('No mark.');
        $filled = fn (AttendanceMark $mark): array => array_keys(array_filter($mark->getAttributes(), fn (mixed $value): bool => $value !== null));

        expect($filled($bulk))->toEqualCanonicalizing($filled($recorded))
            ->and($bulk->tenant_id)->toBe($recorded->tenant_id)
            ->and($bulk->work_center_id)->toBe($recorded->work_center_id)
            ->and($bulk->created_at->equalTo($bulk->marked_at ?? now()))->toBeTrue()
            ->and(Ramsey\Uuid\Uuid::fromString($bulk->id)->getFields()->getVersion())->toBe(4);
    },
];
