<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Support\WorkedDay;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

mutates(WorkedDay::class);

/**
 * @param  list<array{MarkType, string}>  $marks
 * @return list<AttendanceMark>
 */
function dayOfMarks(array $marks): array
{
    return array_map(fn (array $mark): AttendanceMark => new AttendanceMark(['type' => $mark[0], 'marked_at' => CarbonImmutable::parse($mark[1], 'UTC')]), $marks);
}

it('counts the breaks of a day, a break the day ended in included', function (array $marks, int $breaks, int $worked): void {
    $day = dayOfMarks($marks);

    expect(WorkedDay::breakMinutes($day))->toBe($breaks)
        ->and(WorkedDay::hours(CarbonImmutable::parse('2027-01-05'), $day, null)->workedMinutes)->toBe($worked);
})->with([
    'none' => [[[MarkType::Start, '2027-01-05 08:00'], [MarkType::End, '2027-01-05 16:00']], 0, 480],
    'two' => [[
        [MarkType::Start, '2027-01-05 08:00'], [MarkType::BreakStart, '2027-01-05 10:00'], [MarkType::BreakEnd, '2027-01-05 10:15'],
        [MarkType::BreakStart, '2027-01-05 13:00'], [MarkType::BreakEnd, '2027-01-05 13:59:59'], [MarkType::End, '2027-01-05 17:00'],
    ], 74, 465],
    'ended during one' => [[[MarkType::Start, '2027-01-05 08:00'], [MarkType::BreakStart, '2027-01-05 15:00'], [MarkType::End, '2027-01-05 15:30']], 30, 420],
    'still on one' => [[[MarkType::Start, '2027-01-05 08:00'], [MarkType::BreakStart, '2027-01-05 15:00']], 0, 420],
]);

/** A schedule that starts at 09:00 (540) on Tuesday the 5th, and rests on Wednesday the 6th. */
function lateSchedule(?int $start = 540): AttendanceSchedule
{
    return (new AttendanceSchedule)->forceFill([
        'minutes_by_weekday' => [1 => 480, 2 => 480, 3 => 0, 4 => 480, 5 => 480, 6 => 0, 7 => 0],
        'starts_by_weekday' => $start === null ? null : [1 => $start, 2 => $start, 3 => $start, 4 => $start, 5 => $start, 6 => null, 7 => null],
    ]);
}

/** The lateness of a day whose first mark is a start at `$at` (Mexico City time), against 09:00 and a 10-minute tolerance unless told otherwise. */
function lateness(string $at, ?AttendanceSchedule $schedule = null, string $date = '2027-01-05', int $tolerance = 10, bool $away = false): ?int
{
    $utc = CarbonImmutable::parse($at, 'America/Mexico_City')->utc()->format('Y-m-d H:i');
    $marks = $at === '' ? [] : dayOfMarks([[MarkType::Start, $utc]]);

    return WorkedDay::hours(CarbonImmutable::parse($date, 'America/Mexico_City'), $marks, $schedule ?? lateSchedule(), $tolerance, $away)->lateMinutes;
}

describe('the lateness of a day (informational: it never changes the hours)', function (): void {
    it('is 0 when the first start is early, on time or at the last minute of the tolerance, and counts from the start after it', function (string $at, int $late): void {
        expect(lateness($at))->toBe($late);
    })->with([
        'early' => ['2027-01-05 08:50', 0],
        'on time' => ['2027-01-05 09:00', 0],
        'at the tolerance' => ['2027-01-05 09:10', 0],
        'a minute past it' => ['2027-01-05 09:11', 11],
        'an hour and a half' => ['2027-01-05 10:30', 90],
    ]);

    it('is exact with no tolerance', function (): void {
        expect(lateness('2027-01-05 09:01', tolerance: 0))->toBe(1)->and(lateness('2027-01-05 09:00', tolerance: 0))->toBe(0);
    });

    it('is null with no start time, no mark, a rest day, an approved absence or no schedule', function (): void {
        expect(lateness('2027-01-05 10:00', lateSchedule(null)))->toBeNull()
            ->and(lateness('2027-01-05 10:00', (new AttendanceSchedule)->forceFill(['minutes_by_weekday' => [2 => 480], 'starts_by_weekday' => [2 => null]])))->toBeNull()
            ->and(lateness('', date: '2027-01-05'))->toBeNull()
            ->and(lateness('2027-01-06 10:00', date: '2027-01-06'))->toBeNull()
            ->and(lateness('2027-01-05 10:00', away: true))->toBeNull()
            ->and(WorkedDay::hours(CarbonImmutable::parse('2027-01-05'), dayOfMarks([[MarkType::Start, '2027-01-05 16:00']]), null, 10)->lateMinutes)->toBeNull();
    });

    it('measures the first start of the day, not the one after a break', function (): void {
        $marks = dayOfMarks([[MarkType::Start, '2027-01-05 15:20'], [MarkType::BreakStart, '2027-01-05 18:00'], [MarkType::BreakEnd, '2027-01-05 19:00']]);

        expect(WorkedDay::hours(CarbonImmutable::parse('2027-01-05', 'America/Mexico_City'), $marks, lateSchedule(), 10)->lateMinutes)->toBe(20);
    });

    it('is null for a day whose marks hold no start', function (): void {
        $marks = dayOfMarks([[MarkType::End, '2027-01-05 23:00']]);

        expect(WorkedDay::hours(CarbonImmutable::parse('2027-01-05', 'America/Mexico_City'), $marks, lateSchedule(), 10)->lateMinutes)->toBeNull();
    });

    it('counts a night shift that starts at 22:00 and marks at 00:05 as 125 minutes late, on the day the shift began', function (): void {
        // Starts at 22:00 Tuesday the 5th; marks at 00:05 of the 6th (06:05 UTC).
        $marks = dayOfMarks([[MarkType::Start, '2027-01-06 06:05']]);

        expect(WorkedDay::hours(CarbonImmutable::parse('2027-01-05', 'America/Mexico_City'), $marks, lateSchedule(22 * 60), 10)->lateMinutes)->toBe(125);
    });

    it('leaves the worked and scheduled minutes as they were', function (): void {
        $marks = dayOfMarks([[MarkType::Start, '2027-01-05 15:30'], [MarkType::End, '2027-01-05 23:30']]);
        $date = CarbonImmutable::parse('2027-01-05', 'America/Mexico_City');
        $with = WorkedDay::hours($date, $marks, lateSchedule(), 10);
        $without = WorkedDay::hours($date, $marks, lateSchedule(null), 10);

        expect($with->lateMinutes)->toBe(30)
            ->and([$with->workedMinutes, $with->scheduledMinutes, $with->restDay])->toBe([$without->workedMinutes, $without->scheduledMinutes, $without->restDay])
            ->and($with->workedMinutes)->toBe(480);
    });
});
