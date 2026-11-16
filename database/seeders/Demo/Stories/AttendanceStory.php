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
use App\Modules\Tenancy\Contracts\Companies;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\DemoClock;
use Database\Seeders\Demo\DemoContext;
use Database\Seeders\Demo\DemoPerson;
use Database\Seeders\Demo\DemoRoster;
use Database\Seeders\Demo\DemoStory;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Registro de jornada at Alba del Bajío. Every center has its zones on the map, everyone an agreed schedule, and the
 * company one policy that lets a mark out of zone through, flagged (a prospect marks from wherever the demo is shown). The
 * plant's night shift («Producción turno 2») has worked overtime for months, more before HR acted on the NOM-035
 * evaluation and about half in the last weeks; the morning shift works its schedule. Last week waits for each person's
 * confirmation and three people asked HR to correct a mark. The Oficinas center has its kiosk (the QR screen) activated.
 * Nobody has a linked phone, a passkey or an enrollment link: whoever marks live links a real one, and the passkey is what
 * proves who marks. Every mark goes through RecordMark at its own hour; nothing is marked today.
 *
 * ponytail: RecordMark costs five queries a mark (~7 ms in local Docker), so six months of everyone (~50k marks) would add
 * minutes to prepare(). The full history is told for one person of each production shift and last week for both shifts
 * whole (~1.3k marks); raise HISTORY_SAMPLE or add people to LAST_WEEK if a demo needs more and the time allows it.
 */
final readonly class AttendanceStory implements DemoStory
{
    private const string NIGHT_SHIFT = 'Producción turno 2';

    private const string MORNING_SHIFT = 'Producción turno 1';

    /** How far back the history goes, in weeks: the NOM-035 evaluation closed six months ago. */
    private const int HISTORY_WEEKS = 26;

    /** How many people of each production shift have the whole history. */
    private const int HISTORY_SAMPLE = 1;

    /** The last weeks, after HR acted on the evaluation: the night shift's overtime came down by half. */
    private const int CALMER_WEEKS = 3;

    /** The night shift's overtime minutes on each working day of a week (Monday to Saturday), turned by the person. */
    private const array NIGHT_OVERTIME = [120, 150, 0, 120, 180, 60];

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

    public function __construct(
        private SaveZone $zone,
        private SavePolicy $policy,
        private SaveSchedules $schedules,
        private IssueKioskDevice $issueKiosk,
        private ActivateKioskDevice $activateKiosk,
        private RecordMark $mark,
        private RequestCorrection $correction,
        private CloseDays $closeDays,
        private Companies $companies,
    ) {}

    public function tell(DemoContext $context): void
    {
        $zones = DemoClock::at('-'.(self::HISTORY_WEEKS * 7 + 14).' days', fn (): array => $this->setUp($context));

        DemoClock::at('-30 days', function () use ($context): void {
            // The link of the screen dies on first use; whoever shows the screen live issues it again from the panel.
            $this->activateKiosk->handle($this->issueKiosk->handle($context->center('offices')));
        });

        // One transaction: thousands of marks, one commit.
        DB::transaction(fn () => $this->marks($zones));
        $this->corrections();

        // The nightly close: it opens last week for everyone who marked in it. It runs over every company, as each night.
        $this->closeDays->handle();
        $this->companies->enter($context->tenantId);
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

        foreach ($this->scheduleGroups() as [$shift, $minutes, $starts, $people]) {
            foreach (array_chunk($people, 100) as $chunk) {
                $this->schedules->handle($chunk, $shift, WorkMode::Onsite, $minutes, $starts);
            }
        }

        return $zones;
    }

    /**
     * Who works which schedule: the night shift six nights of 7 h from 22:00 (the night's daily maximum); the morning shift
     * from 6:00 and the rest of the plant and the distribution center from 7:00, 8 h on weekdays and 5 h on Saturday;
     * Monterrey 8 h and 4 h on Saturday, and the offices 8 h Monday to Friday, both from 9:00. All under the 46 h of 2027.
     *
     * @return list<array{ShiftType, array<int, int>, array<int, int|null>, list<string>}>
     */
    private function scheduleGroups(): array
    {
        $groups = [
            'night' => [ShiftType::Night, [1 => 420, 2 => 420, 3 => 420, 4 => 420, 5 => 420, 6 => 420, 7 => 0], 22 * 60],
            'morning' => [ShiftType::Day, [1 => 480, 2 => 480, 3 => 480, 4 => 480, 5 => 480, 6 => 300, 7 => 0], 6 * 60],
            'site' => [ShiftType::Day, [1 => 480, 2 => 480, 3 => 480, 4 => 480, 5 => 480, 6 => 300, 7 => 0], 7 * 60],
            'monterrey' => [ShiftType::Day, [1 => 480, 2 => 480, 3 => 480, 4 => 480, 5 => 480, 6 => 240, 7 => 0], 9 * 60],
            'offices' => [ShiftType::Day, [1 => 480, 2 => 480, 3 => 480, 4 => 480, 5 => 480, 6 => 0, 7 => 0], 9 * 60],
        ];
        $people = [];

        foreach (DemoRoster::people() as $person) {
            $people[$this->scheduleOf($person)][] = DemoRoster::employeeId($person->key);
        }

        return array_map(fn (string $group): array => [
            $groups[$group][0],
            $groups[$group][1],
            array_map(fn (int $minutes): ?int => $minutes > 0 ? $groups[$group][2] : null, $groups[$group][1]),
            $people[$group],
        ], array_keys($groups));
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
     * The production shifts' marks: the whole history for a sample of each, last week for everyone in them. A day starts at
     * its schedule (a few minutes either side) and ends after its hours plus, on the night shift, its overtime.
     *
     * @param  array<string, AttendanceZone>  $zones
     */
    private function marks(array $zones): void
    {
        // Days back from today to last week's Monday.
        $toLastMonday = CarbonImmutable::now(DemoClock::TIMEZONE)->isoWeekday() - 1 + 7;

        foreach ([self::NIGHT_SHIFT, self::MORNING_SHIFT] as $area) {
            foreach ($this->shift($area) as $i => $person) {
                $weeks = $i < self::HISTORY_SAMPLE ? self::HISTORY_WEEKS : 1;

                for ($week = $weeks; $week >= 1; $week--) {
                    foreach (range(0, 5) as $weekday) {
                        $this->day($person, $zones['plant'], $toLastMonday + 7 * ($week - 1) - $weekday, $i, $week, $weekday);
                    }
                }
            }
        }
    }

    private function day(DemoPerson $person, AttendanceZone $zone, int $daysAgo, int $i, int $week, int $weekday): void
    {
        $night = $person->area === self::NIGHT_SHIFT;
        $start = ($night ? 22 * 60 : 6 * 60) + ($i + $weekday) % 5 - 2;
        $hours = $night ? 420 : ($weekday === 5 ? 300 : 480);
        $overtime = $night ? intdiv(self::NIGHT_OVERTIME[($weekday + $i) % 6], $week <= self::CALMER_WEEKS ? 2 : 1) : 0;
        $id = DemoRoster::employeeId($person->key);
        $context = new MarkContext(MarkMethod::Geofence, $zone->id, $zone->latitude + 0.0001 * ($i % 3), $zone->longitude, 8.0 + $i % 10, 11.1 * ($i % 3), null);

        DemoClock::at("-{$daysAgo} days", fn (): AttendanceMark => $this->mark->handle($id, MarkType::Start, $context), $start);

        if (! $this->forgetsTheEnd($person, $week, $weekday)) {
            DemoClock::at("-{$daysAgo} days", fn (): AttendanceMark => $this->mark->handle($id, MarkType::End, $context), $start + $hours + $overtime);
        }
    }

    /** The second night-shift person forgot to mark the end of last Saturday's night: their request is pending. */
    private function forgetsTheEnd(DemoPerson $person, int $week, int $weekday): bool
    {
        return $person->key === $this->shift(self::NIGHT_SHIFT)[self::HISTORY_SAMPLE]->key && $week === 1 && $weekday === 5;
    }

    /**
     * Three requests waiting for HR, asked yesterday from people who are not kept for the door: the end someone forgot, a
     * start marked late and a night that ran two hours longer than marked.
     */
    private function corrections(): void
    {
        $night = $this->shift(self::NIGHT_SHIFT);
        $morning = $this->shift(self::MORNING_SHIFT);
        $lastSaturday = CarbonImmutable::now(DemoClock::TIMEZONE)->subWeek()->startOfWeek()->addDays(5)->startOfDay();
        $forgot = DemoRoster::employeeId($night[self::HISTORY_SAMPLE]->key);
        $late = DemoRoster::employeeId($morning[self::HISTORY_SAMPLE]->key);
        $longer = DemoRoster::employeeId($night[self::HISTORY_SAMPLE + 1]->key);
        $lateStart = $this->lastWeekMark($late, MarkType::Start, 2);
        $shortEnd = $this->lastWeekMark($longer, MarkType::End, 1);

        DemoClock::at('-1 day', function () use ($forgot, $late, $longer, $lastSaturday, $lateStart, $shortEnd): void {
            $this->correction->handle($forgot, null, MarkType::End, $lastSaturday->addMinutes(22 * 60 + 420 + 60), 'Se me descargó el teléfono y no pude marcar la salida del sábado.');
            $this->correction->handle($late, $lateStart->id, MarkType::Start, $this->at($lateStart)->subMinutes(25), 'Llegué a tiempo, pero no tenía señal en el estacionamiento y marqué tarde.');
            $this->correction->handle($longer, $shortEnd->id, MarkType::End, $this->at($shortEnd)->addHours(2), 'Me quedé dos horas más a cubrir una vacante y marqué la salida antes de tiempo.');
        });
    }

    /** The person's mark of a type on a day of last week (0 Monday), by the local date of its day's start. */
    private function lastWeekMark(string $employeeId, MarkType $type, int $weekday): AttendanceMark
    {
        $day = CarbonImmutable::now(DemoClock::TIMEZONE)->subWeek()->startOfWeek()->addDays($weekday)->startOfDay();

        return AttendanceMark::query()->where('employee_id', $employeeId)->where('type', $type)
            ->where('marked_at', '>=', $day->utc())->orderBy('marked_at')->firstOrFail();
    }

    private function at(AttendanceMark $mark): CarbonImmutable
    {
        return $mark->marked_at ?? throw new LogicException("Mark [{$mark->id}] has no time.");
    }

    /**
     * The people of a production shift, the door's last: the sample and the requests come from people a prospect never
     * takes.
     *
     * @return list<DemoPerson>
     */
    private function shift(string $area): array
    {
        $people = array_filter(DemoRoster::people(), fn (DemoPerson $person): bool => $person->centerKey === 'plant' && $person->area === $area);
        usort($people, fn (DemoPerson $a, DemoPerson $b): int => $a->reservedForDoor <=> $b->reservedForDoor);

        return $people;
    }
}
