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
