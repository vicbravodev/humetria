<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Stories;

use App\Modules\Attendance\Actions\ActivateKioskDevice;
use App\Modules\Attendance\Actions\CloseDays;
use App\Modules\Attendance\Actions\IssueKioskDevice;
use App\Modules\Attendance\Actions\RecordMark;
use App\Modules\Attendance\Actions\RequestCorrection;
use App\Modules\Attendance\Actions\SavePolicy;
use App\Modules\Attendance\Actions\SaveSchedules;
use App\Modules\Attendance\Actions\SaveZone;
use App\Modules\Attendance\Data\MarkContext;
use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Enums\OutOfZoneRule;
use App\Modules\Attendance\Enums\ShiftType;
use App\Modules\Attendance\Enums\WorkMode;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceZone;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\DemoClock;
use Database\Seeders\Demo\DemoContext;
use Database\Seeders\Demo\DemoPerson;
use Database\Seeders\Demo\DemoRoster;
use Database\Seeders\Demo\DemoStory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LogicException;

/**
 * Registro de jornada at Alba del Bajío. Every center has its zones on the map, everyone an agreed schedule, and the
 * company one policy that lets a mark out of zone through, flagged (a prospect marks from wherever the demo is shown). The
 * plant's two production shifts have six months of days, everyone else the last four weeks, all up to yesterday: a night
 * that would end today was not worked yet, and nobody has marked today. The night shift («Producción turno 2») has worked
 * overtime all along, about half since HR acted on the NOM-035 evaluation; everyone else works their schedule. The
 * Oficinas center marks with the QR of its kiosk, activated a month ago. Last week waits for each person's confirmation and
 * three people asked HR to correct a mark. Nobody has a linked phone, a passkey or an enrollment link: whoever marks live
 * links a real one, and the passkey is what proves who marks.
 *
 * The months are history, not marking: like the spec's other volumes they are inserted in bulk through the module's model
 * (RecordMark is one row a mark, five queries each, minutes for ~25k marks). Each row is what RecordMark writes, every
 * person's sequence is checked with MarkType::allowedAfter first, and the three people who ask for corrections mark their
 * last two weeks through RecordMark itself.
 */
final readonly class AttendanceStory implements DemoStory
{
    private const string NIGHT_SHIFT = 'Producción turno 2';

    private const string MORNING_SHIFT = 'Producción turno 1';

    /** The plant's production shifts go back six months, to the NOM-035 evaluation; everyone else four weeks. */
    private const int SHIFT_WEEKS = 26;

    private const int OTHERS_WEEKS = 4;

    /** The last weeks, after HR acted on the evaluation: the night shift's overtime came down by half. */
    private const int CALMER_DAYS = 21;

    /** The night shift's overtime minutes by ISO weekday (Monday to Saturday), turned by the person. */
    private const array NIGHT_OVERTIME = [120, 150, 0, 120, 180, 60];

    private const int CHUNK = 1000;

    /**
     * By center key: its zones (name, latitude, longitude, radius in meters).
     *
     * @var array<string, list<array{string, float, float, int}>>
     */
    private const array ZONES = [
        'plant' => [['Nave de producción', 20.706500, -100.443600, 150], ['Almacén y andenes', 20.707600, -100.442100, 80]],
        'distribution' => [['Patio y andenes', 20.617000, -100.293000, 120]],
        'offices' => [['Edificio corporativo', 20.593000, -100.390000, 60]],
        'monterrey' => [['Sucursal', 25.686600, -100.316100, 60]],
    ];

    /**
     * The schedules, by group: the night shift six nights of 7 h from 22:00 (the night's daily maximum); the morning shift
     * from 6:00 and the rest of the plant and the distribution center from 7:00, 8 h on weekdays and 5 h on Saturday;
     * Monterrey 8 h and 4 h on Saturday, and the offices 8 h Monday to Friday, both from 9:00. All under the 46 h of 2027.
     *
     * @var array<string, array{ShiftType, array<int, int>, int}>
     */
    private const array SCHEDULES = [
        'night' => [ShiftType::Night, [1 => 420, 2 => 420, 3 => 420, 4 => 420, 5 => 420, 6 => 420, 7 => 0], 22 * 60],
        'morning' => [ShiftType::Day, [1 => 480, 2 => 480, 3 => 480, 4 => 480, 5 => 480, 6 => 300, 7 => 0], 6 * 60],
        'site' => [ShiftType::Day, [1 => 480, 2 => 480, 3 => 480, 4 => 480, 5 => 480, 6 => 300, 7 => 0], 7 * 60],
        'monterrey' => [ShiftType::Day, [1 => 480, 2 => 480, 3 => 480, 4 => 480, 5 => 480, 6 => 240, 7 => 0], 9 * 60],
        'offices' => [ShiftType::Day, [1 => 480, 2 => 480, 3 => 480, 4 => 480, 5 => 480, 6 => 0, 7 => 0], 9 * 60],
    ];

    public function __construct(
        private SaveZone $zone,
        private SavePolicy $policy,
        private SaveSchedules $schedules,
        private IssueKioskDevice $issueKiosk,
        private ActivateKioskDevice $activateKiosk,
        private RecordMark $mark,
        private RequestCorrection $correction,
        private CloseDays $closeDays,
    ) {}

    public function tell(DemoContext $context): void
    {
        $zones = DemoClock::at('-'.(self::SHIFT_WEEKS * 7 + 14).' days', fn (): array => $this->setUp($context));

        DemoClock::at('-30 days', function () use ($context): void {
            // The link of the screen dies on first use; whoever shows the screen live issues it again from the panel.
            $this->activateKiosk->handle($this->issueKiosk->handle($context->center('offices')));
        });

        $this->history($context, $zones);
        $this->corrections();

        // The nightly close, for the demo only: it opens last week for everyone who marked in it.
        $this->closeDays->handle($context->tenantId);
    }

    /** @return array<string, AttendanceZone> the first zone of each center, by center key */
    private function setUp(DemoContext $context): array
    {
        $zones = [];

        foreach (self::ZONES as $center => $list) {
            foreach ($list as [$name, $lat, $lng, $radius]) {
                $zone = $this->zone->handle($context->center($center), $name, $lat, $lng, $radius);
                $zones[$center] ??= $zone;
            }
        }

        $this->policy->handle(OutOfZoneRule::AllowAndFlag, 100, false, true, 24);
        $people = [];

        foreach (DemoRoster::people() as $person) {
            $people[$this->scheduleOf($person)][] = DemoRoster::employeeId($person->key);
        }

        foreach (self::SCHEDULES as $group => [$shift, $minutes, $start]) {
            $starts = array_map(fn (int $day): ?int => $day > 0 ? $start : null, $minutes);

            foreach (array_chunk($people[$group], 100) as $chunk) {
                $this->schedules->handle($chunk, $shift, WorkMode::Onsite, $minutes, $starts);
            }
        }

        return $zones;
    }

    private function scheduleOf(DemoPerson $person): string
    {
        return match (true) {
            $person->centerKey === 'plant' && $person->area === self::NIGHT_SHIFT => 'night',
            $person->centerKey === 'plant' && $person->area === self::MORNING_SHIFT => 'morning',
            $person->centerKey === 'monterrey', $person->centerKey === 'offices' => $person->centerKey,
            default => 'site',
        };
    }

    /**
     * Everyone's days up to yesterday: in bulk, except the last two weeks of the people who ask for corrections, which go
     * through RecordMark at their own hour.
     *
     * @param  array<string, AttendanceZone>  $zones
     */
    private function history(DemoContext $context, array $zones): void
    {
        $centers = array_map(fn (string $key): string => $context->center($key), array_combine(array_keys(DemoRoster::CENTERS), array_keys(DemoRoster::CENTERS)));
        $live = array_map(fn (DemoPerson $person): string => $person->key, $this->askers());
        $lastMonday = CarbonImmutable::now(DemoClock::TIMEZONE)->startOfDay()->subWeek()->startOfWeek();
        $rows = [];
        $recorded = [];

        foreach (DemoRoster::people() as $n => $person) {
            $last = null;
            $days = $this->days($person, $n);

            foreach ($days as $d => [$daysAgo, $start, $end, $date]) {
                $how = $this->markContext($person, $zones[$person->centerKey], $n + $daysAgo);
                $marks = [[MarkType::Start, $start], ...($person->key === $live[0] && $d === array_key_last($days) ? [] : [[MarkType::End, $end]])];

                foreach ($marks as [$type, $minute]) {
                    if (! in_array($type, MarkType::allowedAfter($last), true)) {
                        throw new LogicException("The demo's marks of [{$person->key}] do not follow each other.");
                    }

                    $last = $type;

                    if (in_array($person->key, $live, true) && $date->greaterThanOrEqualTo($lastMonday)) {
                        $recorded[] = [$person, $type, $daysAgo, $minute, $how];
                    } else {
                        $rows[] = $this->row($how, $context->tenantId, $person, $centers[$person->centerKey], $type, $date->addMinutes($minute));
                    }
                }
            }
        }

        DB::transaction(function () use ($rows): void {
            foreach (array_chunk($rows, self::CHUNK) as $chunk) {
                AttendanceMark::query()->insert($chunk);
            }
        });

        foreach ($recorded as [$person, $type, $daysAgo, $minute, $how]) {
            DemoClock::at("-{$daysAgo} days", fn (): AttendanceMark => $this->mark->handle(DemoRoster::employeeId($person->key), $type, $how), $minute);
        }

        Log::info('demo.attendance_seeded', ['bulk_marks' => count($rows), 'recorded_marks' => count($recorded)]);
    }

    /**
     * A person's working days up to yesterday, oldest first: how many days ago, the minutes after that day's midnight they
     * start and end, and the local date. A day starts at its schedule, a few minutes either side, and ends after its hours
     * plus, on the night shift, its overtime; a night that would end today is left out.
     *
     * @return list<array{int, int, int, CarbonImmutable}>
     */
    private function days(DemoPerson $person, int $n): array
    {
        $today = CarbonImmutable::now(DemoClock::TIMEZONE)->startOfDay();
        [, $minutes, $start] = self::SCHEDULES[$this->scheduleOf($person)];
        $night = $this->scheduleOf($person) === 'night';
        $weeks = $person->centerKey === 'plant' && in_array($person->area, [self::NIGHT_SHIFT, self::MORNING_SHIFT], true) ? self::SHIFT_WEEKS : self::OTHERS_WEEKS;
        $days = [];

        for ($daysAgo = $weeks * 7; $daysAgo >= 1; $daysAgo--) {
            $date = $today->subDays($daysAgo);
            $hours = $minutes[$date->isoWeekday()];
            $from = $start + ($n + $daysAgo) % 5 - 2;
            $overtime = $night ? intdiv(self::NIGHT_OVERTIME[($date->isoWeekday() + $n) % 6], $daysAgo <= self::CALMER_DAYS ? 2 : 1) : 0;
            $to = $from + $hours + $overtime;

            if ($hours > 0 && $date->addMinutes($to)->lessThan($today)) {
                $days[] = [$daysAgo, $from, $to, $date];
            }
        }

        return $days;
    }

    /** At the plant and the other centers, inside the zone by the geofence; at Oficinas with the kiosk's QR, a third of the days with no location. */
    private function markContext(DemoPerson $person, AttendanceZone $zone, int $seed): MarkContext
    {
        if ($person->centerKey === 'offices' && $seed % 3 === 0) {
            return new MarkContext(MarkMethod::Qr, null, null, null, null, null, null);
        }

        $method = $person->centerKey === 'offices' ? MarkMethod::QrAndGeofence : MarkMethod::Geofence;

        return new MarkContext($method, $zone->id, $zone->latitude + 0.0001 * ($seed % 3), $zone->longitude, 8.0 + $seed % 10, 11.1 * ($seed % 3), null);
    }

    /**
     * The row RecordMark would write for the mark: a random id, the person's company and center, the time in UTC (created
     * when marked) and how it was made.
     *
     * @return array<string, string|float|null>
     */
    private function row(MarkContext $context, string $tenantId, DemoPerson $person, string $centerId, MarkType $type, CarbonImmutable $at): array
    {
        $time = $at->utc()->format('Y-m-d H:i:s.uP');

        return [
            'id' => Str::uuid()->toString(),
            'tenant_id' => $tenantId,
            'employee_id' => DemoRoster::employeeId($person->key),
            'work_center_id' => $centerId,
            'type' => $type->value,
            'marked_at' => $time,
            'method' => $context->method->value,
            'zone_id' => $context->zoneId,
            'latitude' => $context->latitude,
            'longitude' => $context->longitude,
            'accuracy_m' => $context->accuracy,
            'distance_m' => $context->distance,
            'device_id' => $context->deviceId,
            'created_at' => $time,
        ];
    }

    /**
     * Three requests waiting for HR, asked yesterday: the end someone forgot (their last night), a start marked late and a
     * night that ran two hours longer than marked.
     */
    private function corrections(): void
    {
        [$forgot, $longer, $late] = array_map(fn (DemoPerson $person): string => DemoRoster::employeeId($person->key), $this->askers());
        $open = AttendanceMark::query()->where('employee_id', $forgot)->latest('marked_at')->firstOrFail();
        $lateStart = $this->lastWeekMark($late, MarkType::Start, 2);
        $shortEnd = $this->lastWeekMark($longer, MarkType::End, 1);

        DemoClock::at('-1 day', function () use ($forgot, $late, $longer, $open, $lateStart, $shortEnd): void {
            $this->correction->handle($forgot, null, MarkType::End, $this->at($open)->addMinutes(480), 'Se me descargó el teléfono y no pude marcar la salida del turno.');
            $this->correction->handle($late, $lateStart->id, MarkType::Start, $this->at($lateStart)->subMinutes(25), 'Llegué a tiempo, pero no tenía señal en el estacionamiento y marqué tarde.');
            $this->correction->handle($longer, $shortEnd->id, MarkType::End, $this->at($shortEnd)->addHours(2), 'Me quedé dos horas más a cubrir una vacante y marqué la salida antes de tiempo.');
        });
    }

    /** The person's first mark of a type from a day of last week on (0 Monday). */
    private function lastWeekMark(string $employeeId, MarkType $type, int $weekday): AttendanceMark
    {
        $day = CarbonImmutable::now(DemoClock::TIMEZONE)->startOfDay()->subWeek()->startOfWeek()->addDays($weekday);

        return AttendanceMark::query()->where('employee_id', $employeeId)->where('type', $type)
            ->where('marked_at', '>=', $day->utc())->orderBy('marked_at')->firstOrFail();
    }

    private function at(AttendanceMark $mark): CarbonImmutable
    {
        return $mark->marked_at ?? throw new LogicException("Mark [{$mark->id}] has no time.");
    }

    /**
     * Who asks for corrections, none of them kept for the door: two of the night shift (the one who forgot an end, the one
     * whose night ran longer) and one of the morning shift (a late start).
     *
     * @return array{DemoPerson, DemoPerson, DemoPerson}
     */
    private function askers(): array
    {
        $night = $this->shift(self::NIGHT_SHIFT);

        return [$night[0], $night[1], $this->shift(self::MORNING_SHIFT)[0]];
    }

    /** @return list<DemoPerson> the people of a production shift who are not kept for the door */
    private function shift(string $area): array
    {
        return array_values(array_filter(DemoRoster::people(), fn (DemoPerson $person): bool => $person->centerKey === 'plant' && $person->area === $area && ! $person->reservedForDoor));
    }
}
