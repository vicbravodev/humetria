<?php

declare(strict_types=1);

use App\Modules\Attendance\Actions\CloseDays;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Events\DayClosed;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Attendance\Support\EffectiveMarks;
use App\Modules\Attendance\Support\PersonWeek;
use App\Modules\Attendance\Support\PunchDay;
use App\Modules\Attendance\Support\WeekPeriod;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Event;

/*
 * The stress cases of the design of time zones (§5): the same company with centers in four zones. Each mark is stored in
 * UTC with the zone of where it was taken; a day belongs to the date of its start there; a week, to the zone of the
 * person's center; the hours come from the instants.
 */
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->tijuana = WorkCenter::factory()->for($this->tenant)->create(['timezone' => 'America/Tijuana']);
    $this->mexico = WorkCenter::factory()->for($this->tenant)->create(['timezone' => 'America/Mexico_City']);
    $this->cancun = WorkCenter::factory()->for($this->tenant)->create(['timezone' => 'America/Cancun']);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->tijuana->id]);
    $this->bob = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->mexico->id]);
    workIn($this->tenant);
});

/** A mark of `$person` at a local time of `$zone`, stamped with that zone (and another center when away). */
function zonedMark(Employee $person, MarkType $type, string $local, string $zone, ?string $awayCenterId = null): AttendanceMark
{
    return AttendanceMark::factory()->create([
        'tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'work_center_id' => $person->work_center_id,
        'type' => $type, 'marked_at' => CarbonImmutable::parse($local, $zone)->utc(), 'timezone' => $zone, 'away_center_id' => $awayCenterId,
    ]);
}

/**
 * @return list<array<string, mixed>>
 */
function weekDays(Employee $person, string $date, string $zone): array
{
    $days = PersonWeek::of($person->id, $person->name, WeekPeriod::ofDate(CarbonImmutable::parse($date), $zone), 'acme')['days'];

    return is_array($days) ? array_values($days) : [];
}

it('puts a mark at 23:59:59 and one at 00:00:07 of the center on their own days, in its zone', function (): void {
    $late = zonedMark($this->ana, MarkType::Start, '2026-11-09 23:59:59', 'America/Tijuana');
    zonedMark($this->ana, MarkType::End, '2026-11-09 23:59:59.5', 'America/Tijuana');
    $early = zonedMark($this->ana, MarkType::Start, '2026-11-10 00:00:07', 'America/Tijuana');

    $byDay = EffectiveMarks::byDay($this->ana->id, WeekPeriod::ofDate(CarbonImmutable::parse('2026-11-09'), 'America/Tijuana'));

    // In Mexico City both would fall on the 10th, two hours later.
    expect($byDay['2026-11-09'][0]->id)->toBe($late->id)
        ->and($byDay['2026-11-10'][0]->id)->toBe($early->id);
});

it('gives a start at the shift\'s time in Tijuana no lateness, as one in Mexico City', function (): void {
    foreach ([$this->ana, $this->bob] as $person) {
        AttendanceSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $person->id, 'starts_by_weekday' => [1 => 360, 2 => 360, 3 => 360, 4 => 360, 5 => 360, 6 => null, 7 => null]]);
    }
    zonedMark($this->ana, MarkType::Start, '2026-11-10 06:00', 'America/Tijuana');
    zonedMark($this->bob, MarkType::Start, '2026-11-10 06:00', 'America/Mexico_City');

    expect(weekDays($this->ana, '2026-11-10', 'America/Tijuana')[1]['lateMinutes'])->toBe(0)
        ->and(weekDays($this->bob, '2026-11-10', 'America/Mexico_City')[1]['lateMinutes'])->toBe(0);
});

it('shows a mark taken at another center at the hour there, flagged and with no lateness', function (): void {
    AttendanceSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->bob->id, 'starts_by_weekday' => [1 => 360, 2 => 360, 3 => 360, 4 => 360, 5 => 360, 6 => null, 7 => null]]);
    // Bob, of Mexico City, starts at 06:00 in Tijuana: 08:00 in Mexico City, and not two hours late.
    zonedMark($this->bob, MarkType::Start, '2026-11-10 06:00', 'America/Tijuana', $this->tijuana->id);

    $day = weekDays($this->bob, '2026-11-10', 'America/Mexico_City')[1];

    expect($day['lateMinutes'])->toBeNull()
        ->and($day['marks'][0])->toMatchArray(['time' => '06:00', 'clock' => 'America/Tijuana', 'away' => true, 'nextDay' => false]);
});

it('keeps each mark in its zone when the person moves from one center to another in the week', function (): void {
    zonedMark($this->ana, MarkType::Start, '2026-11-09 09:00', 'America/Mexico_City');
    zonedMark($this->ana, MarkType::End, '2026-11-09 17:00', 'America/Mexico_City');
    zonedMark($this->ana, MarkType::Start, '2026-11-12 09:00', 'America/Tijuana');

    $days = weekDays($this->ana, '2026-11-09', 'America/Tijuana');

    expect($days[0]['marks'][0])->toMatchArray(['time' => '09:00', 'clock' => 'America/Mexico_City'])
        ->and($days[0]['workedMinutes'])->toBe(480)
        ->and($days[3]['marks'][0])->toMatchArray(['time' => '09:00', 'clock' => null]);
});

it('never moves an old mark to another day when its center changes zone', function (): void {
    zonedMark($this->ana, MarkType::Start, '2026-11-09 23:30', 'America/Tijuana');

    $this->tijuana->update(['timezone' => 'America/Cancun']);
    // Read today in Cancún, where that instant was already 02:30 of the 10th.
    $days = weekDays($this->ana, '2026-11-09', 'America/Cancun');

    expect($days[0]['marks'][0])->toMatchArray(['time' => '23:30', 'clock' => 'America/Tijuana'])
        ->and($days[1]['marks'])->toBe([]);
});

it('orders the two marks of the repeated hour of 1 November by their instants', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-01 10:00', 'UTC'));
    // 01:30 of summer time (08:30 UTC), then 01:30 again, an hour later, of winter time (09:30 UTC).
    AttendanceMark::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->tijuana->id, 'type' => MarkType::Start, 'marked_at' => CarbonImmutable::parse('2026-11-01 08:30', 'UTC'), 'timezone' => 'America/Tijuana']);
    AttendanceMark::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->tijuana->id, 'type' => MarkType::End, 'marked_at' => CarbonImmutable::parse('2026-11-01 09:30', 'UTC'), 'timezone' => 'America/Tijuana']);

    expect(PunchDay::of($this->ana->id, 'America/Tijuana')['marks'])->toBe([
        ['type' => 'start', 'time' => '01:30', 'date' => '2026-11-01', 'clock' => null],
        ['type' => 'end', 'time' => '01:30', 'date' => '2026-11-01', 'clock' => null],
    ])->and(weekDays($this->ana, '2026-11-01', 'America/Tijuana')[6]['workedMinutes'])->toBe(60);
});

it('starts the day of Marcar at midnight of the person\'s center', function (): void {
    // 01:00 of the 10th in Mexico City is still 23:00 of the 9th in Tijuana: Ana's day is the 9th.
    $this->travelTo(CarbonImmutable::parse('2026-11-10 07:00', 'UTC'));
    zonedMark($this->ana, MarkType::Start, '2026-11-09 18:00', 'America/Tijuana');
    zonedMark($this->ana, MarkType::End, '2026-11-09 21:00', 'America/Tijuana');

    expect(PunchDay::of($this->ana->id, 'America/Tijuana')['marks'])->toHaveCount(2)
        ->and(PunchDay::of($this->ana->id, 'America/Mexico_City')['marks'])->toBe([]);
});

it('closes each center\'s day before yesterday at its own midnight', function (): void {
    Event::fake([DayClosed::class]);
    foreach ([$this->ana, $this->bob] as $person) {
        AttendanceSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $person->id, 'minutes_by_weekday' => [1 => 480, 2 => 480, 3 => 480, 4 => 480, 5 => 480, 6 => 480, 7 => 480]]);
    }
    // Wednesday 11 November, 07:15 UTC: 01:15 in Mexico City (today the 11th), still 23:15 of the 10th in Tijuana.
    $this->travelTo(CarbonImmutable::parse('2026-11-11 07:15', 'UTC'));

    resolve(CloseDays::class)->handle($this->tenant->id);

    Event::assertDispatched(DayClosed::class, fn (DayClosed $event): bool => $event->employeeId === $this->bob->id && $event->date === '2026-11-09');
    Event::assertDispatched(DayClosed::class, fn (DayClosed $event): bool => $event->employeeId === $this->ana->id && $event->date === '2026-11-08');
});

it('opens a week for confirmation only once it ended in the person\'s center', function (): void {
    zonedMark($this->ana, MarkType::Start, '2026-11-03 09:00', 'America/Tijuana');
    zonedMark($this->ana, MarkType::End, '2026-11-03 17:00', 'America/Tijuana');
    zonedMark($this->bob, MarkType::Start, '2026-11-03 09:00', 'America/Mexico_City');
    zonedMark($this->bob, MarkType::End, '2026-11-03 17:00', 'America/Mexico_City');
    // Monday 9 November, 07:00 UTC: 01:00 of Monday in Mexico City, 23:00 of Sunday in Tijuana.
    $this->travelTo(CarbonImmutable::parse('2026-11-09 07:00', 'UTC'));

    resolve(CloseDays::class)->handle($this->tenant->id);

    expect(WeeklyConfirmation::query()->pluck('employee_id')->all())->toBe([$this->bob->id]);

    $this->travelTo(CarbonImmutable::parse('2026-11-09 08:00', 'UTC'));
    resolve(CloseDays::class)->handle($this->tenant->id);

    expect(WeeklyConfirmation::query()->where('employee_id', $this->ana->id)->sole()->week_start->toDateString())->toBe('2026-11-02');
});
