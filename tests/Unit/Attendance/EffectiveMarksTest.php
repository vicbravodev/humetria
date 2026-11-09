<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Support\EffectiveMarks;
use App\Modules\Attendance\Support\WeekPeriod;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

mutates(EffectiveMarks::class);

beforeEach(function (): void {
    config(['app.display_timezone' => 'America/Mexico_City']);
    $this->tenant = Tenant::factory()->create();
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    workIn($this->tenant);
});

/** A mark of Ana at a local time of Mexico City. */
function anaMarks(object $test, MarkType $type, string $local): AttendanceMark
{
    return AttendanceMark::factory()->create([
        'tenant_id' => $test->tenant->id, 'employee_id' => $test->ana->id, 'work_center_id' => $test->center->id,
        'type' => $type, 'marked_at' => CarbonImmutable::parse($local, 'America/Mexico_City'),
    ]);
}

function weekOf(string $local): WeekPeriod
{
    return WeekPeriod::containing(CarbonImmutable::parse($local, 'America/Mexico_City'));
}

/**
 * @param  list<AttendanceMark>  $marks
 * @return list<string>
 */
function markIds(array $marks): array
{
    return array_map(fn (AttendanceMark $mark): string => $mark->id, $marks);
}

it('gives the week\'s marks in order of time', function (): void {
    $end = anaMarks($this, MarkType::End, '2026-11-04 18:00');
    $start = anaMarks($this, MarkType::Start, '2026-11-04 09:00');
    anaMarks($this, MarkType::Start, '2026-11-10 09:00');
    AttendanceMark::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'marked_at' => CarbonImmutable::parse('2026-11-04 09:00', 'America/Mexico_City')]);

    expect(markIds(EffectiveMarks::forWeek($this->ana->id, weekOf('2026-11-04'))))->toBe([$start->id, $end->id]);
});

it('puts a day that ends after midnight on the local date of its start', function (): void {
    $start = anaMarks($this, MarkType::Start, '2026-11-08 23:59');
    $end = anaMarks($this, MarkType::End, '2026-11-09 00:01');

    expect(markIds(EffectiveMarks::forWeek($this->ana->id, weekOf('2026-11-08'))))->toBe([$start->id, $end->id])
        ->and(EffectiveMarks::forWeek($this->ana->id, weekOf('2026-11-09')))->toBe([])
        ->and(array_map(markIds(...), EffectiveMarks::byDay($this->ana->id, weekOf('2026-11-08'))))->toBe(['2026-11-08' => [$start->id, $end->id]]);
});

it('keeps the end of a Sunday night day that closes 20 hours later, reading the window in UTC', function (): void {
    $start = anaMarks($this, MarkType::Start, '2026-11-08 22:00');
    $end = anaMarks($this, MarkType::End, '2026-11-09 18:00');

    expect(markIds(EffectiveMarks::forWeek($this->ana->id, weekOf('2026-11-08'))))->toBe([$start->id, $end->id]);
});

it('keeps a night that starts on Sunday 22:00 and ends on Monday 06:00 whole in the week of its start', function (): void {
    $start = anaMarks($this, MarkType::Start, '2026-11-08 22:00');
    $end = anaMarks($this, MarkType::End, '2026-11-09 06:00');

    expect(markIds(EffectiveMarks::forWeek($this->ana->id, weekOf('2026-11-08'))))->toBe([$start->id, $end->id])
        ->and(EffectiveMarks::forWeek($this->ana->id, weekOf('2026-11-09')))->toBe([]);
});

it('keeps the end of a day that started in the week however long after the week it comes', function (): void {
    $start = anaMarks($this, MarkType::Start, '2026-11-08 22:00');
    $break = anaMarks($this, MarkType::BreakStart, '2026-11-10 01:00');
    $end = anaMarks($this, MarkType::End, '2026-11-10 02:00');
    anaMarks($this, MarkType::Start, '2026-11-10 09:00');

    expect(markIds(EffectiveMarks::forWeek($this->ana->id, weekOf('2026-11-08'))))->toBe([$start->id, $break->id, $end->id]);
});

it('leaves out of the week the end of a day that started more than a day before it', function (): void {
    $start = anaMarks($this, MarkType::Start, '2026-10-31 20:00');
    $end = anaMarks($this, MarkType::End, '2026-11-02 01:00');
    $next = anaMarks($this, MarkType::Start, '2026-11-02 09:00');

    expect(markIds(EffectiveMarks::forWeek($this->ana->id, weekOf('2026-11-02'))))->toBe([$next->id])
        ->and(markIds(EffectiveMarks::forWeek($this->ana->id, weekOf('2026-10-31'))))->toBe([$start->id, $end->id]);
});

it('starts the week with the first day that opens in it', function (): void {
    anaMarks($this, MarkType::Start, '2026-11-01 22:00');
    anaMarks($this, MarkType::End, '2026-11-02 06:00');
    $start = anaMarks($this, MarkType::Start, '2026-11-02 22:00');

    expect(array_map(markIds(...), EffectiveMarks::byDay($this->ana->id, weekOf('2026-11-02'))))->toBe(['2026-11-02' => [$start->id]]);
});

it('keeps a mark with no start before it on its own local date', function (): void {
    $end = anaMarks($this, MarkType::End, '2026-11-03 18:00');

    expect(array_map(markIds(...), EffectiveMarks::byDay($this->ana->id, weekOf('2026-11-03'))))->toBe(['2026-11-03' => [$end->id]]);
});

it('uses the correction instead of its original', function (): void {
    $start = anaMarks($this, MarkType::Start, '2026-11-04 09:20');
    $correction = AttendanceMark::factory()->correcting($start)->create(['marked_at' => CarbonImmutable::parse('2026-11-04 09:00', 'America/Mexico_City')]);

    expect(markIds(EffectiveMarks::forWeek($this->ana->id, weekOf('2026-11-04'))))->toBe([$correction->id]);
});

it('follows a correction of a correction to the last one', function (): void {
    $start = anaMarks($this, MarkType::Start, '2026-11-04 09:20');
    $first = AttendanceMark::factory()->correcting($start)->create();
    $second = AttendanceMark::factory()->correcting($first)->create(['corrected_by' => $first->corrected_by]);

    expect(markIds(EffectiveMarks::forWeek($this->ana->id, weekOf('2026-11-04'))))->toBe([$second->id]);
});

it('leaves out a mark a correction voided, and the void itself', function (): void {
    $start = anaMarks($this, MarkType::Start, '2026-11-04 09:00');
    AttendanceMark::factory()->correcting($start)->create(['marked_at' => null]);

    expect(EffectiveMarks::forWeek($this->ana->id, weekOf('2026-11-04')))->toBe([]);
});

it('gives the days of several people at once, each with their own window, in two queries (their windows, then their marks)', function (): void {
    // Ana's Saturday night day spills into the week; Bob's last start is weeks before and his marks all count on their own.
    anaMarks($this, MarkType::Start, '2026-11-07 22:00');
    anaMarks($this, MarkType::End, '2026-11-09 03:00');
    $anaStart = anaMarks($this, MarkType::Start, '2026-11-10 09:00');
    $anaEnd = anaMarks($this, MarkType::End, '2026-11-10 17:00');
    $bob = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    $bobMark = fn (MarkType $type, string $local): AttendanceMark => AttendanceMark::factory()->create([
        'tenant_id' => $this->tenant->id, 'employee_id' => $bob->id, 'work_center_id' => $this->center->id,
        'type' => $type, 'marked_at' => CarbonImmutable::parse($local, 'America/Mexico_City'),
    ]);
    $bobMark(MarkType::Start, '2026-10-01 09:00');
    $bobMark(MarkType::End, '2026-11-11 18:00');
    $bobStart = $bobMark(MarkType::Start, '2026-11-12 08:00');
    $bobMark(MarkType::Start, '2026-11-16 08:00');
    $nobody = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    DB::enableQueryLog();

    $days = EffectiveMarks::byDayOf([$this->ana->id, $bob->id, $nobody->id], weekOf('2026-11-10'));

    expect(count(DB::getQueryLog()))->toBe(2)
        ->and(array_map(fn (array $byDay): array => array_map(markIds(...), $byDay), $days))->toBe([
            $this->ana->id => ['2026-11-10' => [$anaStart->id, $anaEnd->id]],
            // His end on Wednesday closes his October day, so it is not the week's.
            $bob->id => ['2026-11-12' => [$bobStart->id]],
            $nobody->id => [],
        ])
        ->and(EffectiveMarks::byDayOf([], weekOf('2026-11-10')))->toBe([]);
});

describe('reading many people at once', function (): void {
    /** A mark of `$person` at a local time of Mexico City. */
    function markOf(object $test, string $personId, MarkType $type, string $local): AttendanceMark
    {
        return AttendanceMark::factory()->create([
            'tenant_id' => $test->tenant->id, 'employee_id' => $personId, 'work_center_id' => $test->center->id,
            'type' => $type, 'marked_at' => CarbonImmutable::parse($local, 'America/Mexico_City'),
        ]);
    }

    it('gives the same days with months of history before the week: only the last day before it is read, then left out', function (): void {
        $people = EmployeeFactory::new()->count(3)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
        $expected = [];
        foreach ($people as $person) {
            foreach (range(1, 20) as $day) {
                markOf($this, $person->id, MarkType::Start, CarbonImmutable::parse('2026-06-01')->addDays($day)->format('Y-m-d').' 09:00');
                markOf($this, $person->id, MarkType::End, CarbonImmutable::parse('2026-06-01')->addDays($day)->format('Y-m-d').' 17:00');
            }
            $start = markOf($this, $person->id, MarkType::Start, '2026-11-10 09:00');
            $end = markOf($this, $person->id, MarkType::End, '2026-11-10 17:00');
            $expected[$person->id] = ['2026-11-10' => [$start->id, $end->id]];
        }

        $days = EffectiveMarks::byDayOf($people->pluck('id')->all(), weekOf('2026-11-10'));

        expect(array_map(fn (array $byDay): array => array_map(markIds(...), $byDay), $days))->toBe($expected);
    });

    it('keeps each person\'s own rules: a start before the week ends there, a Sunday night stays on Sunday', function (): void {
        [$bob, $carla] = [EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]), EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id])];
        // Bob starts on Sunday 8 (the week before) and ends on Monday 9: not the week of the 9th.
        markOf($this, $bob->id, MarkType::Start, '2026-11-08 20:00');
        markOf($this, $bob->id, MarkType::End, '2026-11-09 04:00');
        $bobTuesday = markOf($this, $bob->id, MarkType::Start, '2026-11-10 09:00');
        // Carla's night of Sunday 15 to Monday 16 is the week's last day.
        $carlaStart = markOf($this, $carla->id, MarkType::Start, '2026-11-15 22:00');
        $carlaEnd = markOf($this, $carla->id, MarkType::End, '2026-11-16 06:00');
        markOf($this, $carla->id, MarkType::Start, '2026-11-16 22:00');

        $days = EffectiveMarks::byDayOf([$bob->id, $carla->id], weekOf('2026-11-10'));

        expect(array_map(fn (array $byDay): array => array_map(markIds(...), $byDay), $days))->toBe([
            $bob->id => ['2026-11-10' => [$bobTuesday->id]],
            $carla->id => ['2026-11-15' => [$carlaStart->id, $carlaEnd->id]],
        ]);
    });

    it('never reads another company\'s people or marks', function (): void {
        $globex = Tenant::factory()->create();
        $theirCenter = WorkCenter::factory()->for($globex)->create();
        $theirs = EmployeeFactory::new()->create(['tenant_id' => $globex->id, 'work_center_id' => $theirCenter->id]);
        AttendanceMark::factory()->create(['tenant_id' => $globex->id, 'employee_id' => $theirs->id, 'work_center_id' => $theirCenter->id, 'marked_at' => CarbonImmutable::parse('2026-11-10 09:00', 'America/Mexico_City')]);
        workIn($this->tenant);

        expect(EffectiveMarks::byDayOf([$theirs->id], weekOf('2026-11-10')))->toBe([$theirs->id => []]);
    });

    it('bounds every read of the marks by the company', function (): void {
        anaMarks($this, MarkType::Start, '2026-11-10 09:00');
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        EffectiveMarks::byDayOf([$this->ana->id], weekOf('2026-11-10'));

        expect($queries)->not->toBeEmpty();
        foreach ($queries as $sql) {
            // Each read of the table (the marks, the windows, the corrections) comes with its own tenant_id predicate.
            expect(substr_count($sql, 'tenant_id'))->toBeGreaterThanOrEqual(preg_match_all('/(from|join) "attendance_marks"/', $sql), $sql);
        }
    });

    it('reads no table of another module', function (): void {
        anaMarks($this, MarkType::Start, '2026-11-10 09:00');
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        EffectiveMarks::byDayOf([$this->ana->id], weekOf('2026-11-10'));

        expect($queries)->not->toBeEmpty();
        foreach ($queries as $sql) {
            expect($sql)->not->toContain('employees')->not->toContain('work_centers');
        }
    });

    it('takes no bound from a start a correction voided, nor shows it', function (): void {
        // Ana's Sunday start was voided: her Monday end is a mark with no start, on its own day.
        $voided = anaMarks($this, MarkType::Start, '2026-11-08 20:00');
        AttendanceMark::factory()->correcting($voided)->create(['marked_at' => null]);
        $end = anaMarks($this, MarkType::End, '2026-11-09 04:00');

        expect(array_map(markIds(...), EffectiveMarks::byDayOf([$this->ana->id], weekOf('2026-11-10'))[$this->ana->id]))->toBe(['2026-11-09' => [$end->id]]);
    });

    it('places a start corrected across the week\'s edge by its corrected time', function (): void {
        // Monday 09:00 corrected to Sunday 23:00 (the week before): its day and its end leave the week.
        $monday = anaMarks($this, MarkType::Start, '2026-11-09 09:00');
        $sunday = AttendanceMark::factory()->correcting($monday)->create(['marked_at' => CarbonImmutable::parse('2026-11-08 23:00', 'America/Mexico_City')]);
        anaMarks($this, MarkType::End, '2026-11-09 07:00');
        // Sunday 15 at 23:00 (outside the next week) corrected to Monday 16 at 09:00: out of this week, into the next.
        $late = anaMarks($this, MarkType::Start, '2026-11-15 23:00');
        $next = AttendanceMark::factory()->correcting($late)->create(['marked_at' => CarbonImmutable::parse('2026-11-16 09:00', 'America/Mexico_City')]);
        $tuesday = anaMarks($this, MarkType::Start, '2026-11-10 09:00');

        expect(array_map(markIds(...), EffectiveMarks::byDayOf([$this->ana->id], weekOf('2026-11-10'))[$this->ana->id]))->toBe(['2026-11-10' => [$tuesday->id]])
            ->and(array_map(markIds(...), EffectiveMarks::byDayOf([$this->ana->id], weekOf('2026-11-16'))[$this->ana->id]))->toBe(['2026-11-16' => [$next->id]])
            ->and(array_map(markIds(...), EffectiveMarks::byDayOf([$this->ana->id], weekOf('2026-11-08'))[$this->ana->id])['2026-11-08'] ?? [])->toContain($sunday->id);
    });

    it('reads in the same number of queries for 1, 5 and 30 people', function (int $count): void {
        $people = EmployeeFactory::new()->count($count)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
        foreach ($people as $person) {
            markOf($this, $person->id, MarkType::Start, '2026-10-01 09:00');
            markOf($this, $person->id, MarkType::Start, '2026-11-10 09:00');
        }
        DB::flushQueryLog();
        DB::enableQueryLog();

        EffectiveMarks::byDayOf($people->pluck('id')->all(), weekOf('2026-11-10'));

        expect(count(DB::getQueryLog()))->toBe(2);
    })->with([1, 5, 30]);
});

describe('reading a period of any length', function (): void {
    it('gives the days from one local date to another, both included, in the same two queries', function (): void {
        anaMarks($this, MarkType::Start, '2026-10-31 22:00');
        $saturdayEnd = anaMarks($this, MarkType::End, '2026-11-01 06:00');
        $first = anaMarks($this, MarkType::Start, '2026-11-01 22:00');
        $firstEnd = anaMarks($this, MarkType::End, '2026-11-02 06:00');
        $last = anaMarks($this, MarkType::Start, '2026-11-20 09:00');
        anaMarks($this, MarkType::Start, '2026-11-21 09:00');
        DB::flushQueryLog();
        DB::enableQueryLog();

        $days = EffectiveMarks::byDayBetween([$this->ana->id], CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-20'));

        expect(count(DB::getQueryLog()))->toBe(2)
            ->and(array_map(markIds(...), $days[$this->ana->id]))->toBe([
                // Saturday's night ends on Sunday 1 but is Saturday's: left out with its day.
                '2026-11-01' => [$first->id, $firstEnd->id],
                '2026-11-20' => [$last->id],
            ])
            ->and($saturdayEnd->id)->not->toBeIn(array_merge([], ...array_values($days[$this->ana->id])))
            ->and(EffectiveMarks::byDayBetween([], CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-20')))->toBe([]);
    });
});
