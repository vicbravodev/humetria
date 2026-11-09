<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Models\AttendanceMark;
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
