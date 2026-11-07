<?php

declare(strict_types=1);

use App\Modules\Attendance\Data\DayHours;
use App\Modules\Attendance\Enums\OverLimit;
use App\Modules\Attendance\Enums\ShiftType;
use App\Modules\Attendance\Support\HoursCalculator;
use App\Modules\Attendance\Support\LegalLimits;
use Carbon\CarbonImmutable;

mutates(LegalLimits::class, HoursCalculator::class);

/**
 * Working days from Monday 2027-03-01 on, one entry per day as [worked, scheduled].
 *
 * @param  list<array{int, int}>  $days
 * @return list<DayHours>
 */
function week(array $days, string $monday = '2027-03-01'): array
{
    $start = CarbonImmutable::parse($monday);

    return array_map(
        fn (array $day, int $offset): DayHours => new DayHours($start->addDays($offset), $day[0], $day[1], false),
        $days,
        array_keys($days),
    );
}

it('counts no overtime in five scheduled days of 8 h worked as scheduled', function (): void {
    $summary = HoursCalculator::week(week(array_fill(0, 5, [480, 480])), ShiftType::Day, 2027);

    expect($summary->ordinaryMinutes)->toBe(2400)
        ->and($summary->overtimeMinutes)->toBe(0)
        ->and($summary->doubleMinutes)->toBe(0)
        ->and($summary->tripleMinutes)->toBe(0)
        ->and($summary->restDayMinutes)->toBe(0)
        ->and($summary->flags)->toBe([])
        ->and($summary->overtimeByDay)->toBe([]);
});

it('pays double the hour worked over the 8 scheduled that day', function (): void {
    $summary = HoursCalculator::week(week([[480, 480], [540, 480], [480, 480]]), ShiftType::Day, 2027);

    expect($summary->ordinaryMinutes)->toBe(1440)
        ->and($summary->overtimeMinutes)->toBe(60)
        ->and($summary->doubleMinutes)->toBe(60)
        ->and($summary->tripleMinutes)->toBe(0)
        ->and($summary->overtimeByDay)->toBe(['2027-03-02' => 60]);
});

it('does not offset a short day with another day\'s overtime', function (): void {
    $summary = HoursCalculator::week(week([[420, 480], [540, 480]]), ShiftType::Day, 2027);

    expect($summary->ordinaryMinutes)->toBe(900)
        ->and($summary->overtimeMinutes)->toBe(60);
});

it('pays double up to the year\'s cap and triple the first minute above it', function (int $lastDay, int $double, int $triple): void {
    $summary = HoursCalculator::week(week([[660, 480], [660, 480], [480 + $lastDay, 480]]), ShiftType::Day, 2027);

    expect($summary->overtimeMinutes)->toBe(360 + $lastDay)
        ->and($summary->doubleMinutes)->toBe($double)
        ->and($summary->tripleMinutes)->toBe($triple)
        ->and($summary->flags)->toBe([]);
})->with([
    'exactly 540' => [180, 540, 0],
    '541' => [181, 540, 1],
]);

it('uses the cap of the year it is given', function (): void {
    $summary = HoursCalculator::week(week([[720, 480], [720, 480], [600, 480]]), ShiftType::Day, 2028);

    expect($summary->doubleMinutes)->toBe(600)
        ->and($summary->tripleMinutes)->toBe(0);
});

it('flags a day above 12 hours', function (int $worked, array $flags): void {
    $summary = HoursCalculator::week(week([[$worked, 540]]), ShiftType::Day, 2027);

    expect($summary->flags)->toBe($flags);
})->with([
    '12 h' => [720, []],
    '13 h' => [780, [OverLimit::DayOver12Hours]],
]);

it('flags a day above 12 hours worked on a rest day', function (): void {
    expect(HoursCalculator::week([new DayHours(CarbonImmutable::parse('2027-03-07'), 721, 0, true)], ShiftType::Day, 2027)->flags)->toBe([OverLimit::DayOver12Hours]);
});

it('flags more than 4 overtime hours in a day', function (int $overtime, array $flags): void {
    $summary = HoursCalculator::week(week([[420 + $overtime, 420]]), ShiftType::Day, 2027);

    expect($summary->flags)->toBe($flags);
})->with([
    '4 h' => [240, []],
    '4 h 1 min' => [241, [OverLimit::DailyOvertimeOver4]],
    '5 h' => [300, [OverLimit::DailyOvertimeOver4]],
]);

it('flags overtime on more than 4 days of the week', function (int $days, array $flags): void {
    $worked = array_merge(array_fill(0, $days, [510, 480]), array_fill(0, 5 - $days, [480, 480]));

    expect(HoursCalculator::week(week($worked), ShiftType::Day, 2027)->flags)->toBe($flags);
})->with([
    '4 days' => [4, []],
    '5 days' => [5, [OverLimit::OvertimeDaysOver4]],
]);

it('flags triple time above 4 hours a week', function (int $lastDay, array $flags): void {
    $summary = HoursCalculator::week(week([[720, 480], [720, 480], [720, 480], [480 + $lastDay, 480]]), ShiftType::Day, 2027);

    expect($summary->tripleMinutes)->toBe(180 + $lastDay)
        ->and($summary->doubleMinutes)->toBe(540)
        ->and($summary->flags)->toBe($flags);
})->with([
    'exactly 4 h of triple' => [60, []],
    '4 h of triple and 1 min' => [61, [OverLimit::TripleCapExceeded]],
]);

it('counts a worked rest day apart, not as overtime', function (): void {
    $days = [...week(array_fill(0, 5, [480, 480])), new DayHours(CarbonImmutable::parse('2027-03-06'), 240, 0, true)];

    $summary = HoursCalculator::week($days, ShiftType::Day, 2027);

    expect($summary->restDayMinutes)->toBe(240)
        ->and($summary->ordinaryMinutes)->toBe(2400)
        ->and($summary->overtimeMinutes)->toBe(0)
        ->and($summary->overtimeByDay)->toBe([]);
});

it('moves to overtime the scheduled minutes above the year\'s weekly ordinary maximum, on the days that cross it', function (): void {
    // 2030: 40 h. Six scheduled days of 8 h, given out of order: Saturday's 8 h are overtime.
    $days = array_reverse(week(array_fill(0, 6, [480, 480]), '2030-03-04'));

    $summary = HoursCalculator::week($days, ShiftType::Day, 2030);

    expect($summary->ordinaryMinutes)->toBe(2400)
        ->and($summary->overtimeMinutes)->toBe(480)
        ->and($summary->doubleMinutes)->toBe(480)
        ->and($summary->overtimeByDay)->toBe(['2030-03-09' => 480])
        ->and($summary->flags)->toBe([OverLimit::DailyOvertimeOver4]);
});

it('splits the day that crosses the weekly maximum and adds it to that day\'s own overtime', function (): void {
    // 2027: 46 h = 2760. Six days of 8 h = 2880: 120 min of Saturday are past the maximum, plus 30 worked over schedule.
    $summary = HoursCalculator::week(week([...array_fill(0, 5, [480, 480]), [510, 480]]), ShiftType::Day, 2027);

    expect($summary->ordinaryMinutes)->toBe(2760)
        ->and($summary->overtimeMinutes)->toBe(150)
        ->and($summary->overtimeByDay)->toBe(['2027-03-06' => 150]);
});

it('keeps scheduled minutes above the daily maximum as ordinary when the week stays within its maximum', function (): void {
    // Criterio 2.6: 5 × 9 h 12 min = 46 h, a schedule distributed by agreement.
    $summary = HoursCalculator::week(week(array_fill(0, 5, [552, 552])), ShiftType::Day, 2027);

    expect($summary->ordinaryMinutes)->toBe(2760)
        ->and($summary->overtimeMinutes)->toBe(0);
});

it('summarizes an empty week as nothing', function (): void {
    $summary = HoursCalculator::week([], ShiftType::Night, 2027);

    expect($summary->ordinaryMinutes)->toBe(0)
        ->and($summary->overtimeMinutes)->toBe(0)
        ->and($summary->flags)->toBe([]);
});

it('lists every flag a week breaks, once each', function (): void {
    $summary = HoursCalculator::week(week(array_fill(0, 5, [780, 480])), ShiftType::Day, 2027);

    expect($summary->flags)->toBe([
        OverLimit::DayOver12Hours,
        OverLimit::DailyOvertimeOver4,
        OverLimit::OvertimeDaysOver4,
        OverLimit::TripleCapExceeded,
    ]);
});
