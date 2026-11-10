<?php

declare(strict_types=1);

use App\Modules\Leave\Enums\WorkWeek;
use App\Modules\Leave\Support\StatutoryHolidays;
use App\Modules\Leave\Support\WorkingDays;
use Carbon\CarbonImmutable;

mutates(WorkingDays::class, WorkWeek::class);

function day(string $date): CarbonImmutable
{
    return CarbonImmutable::parse($date);
}

it('knows which days of the week each working week has', function (string $date, bool $monFri, bool $monSat): void {
    expect(WorkWeek::MonFri->includes(day($date)))->toBe($monFri)
        ->and(WorkWeek::MonSat->includes(day($date)))->toBe($monSat);
})->with([
    'monday' => ['2026-11-09', true, true],
    'friday' => ['2026-11-13', true, true],
    'saturday' => ['2026-11-14', false, true],
    'sunday' => ['2026-11-15', false, false],
]);

// Criterio C-04: the week of the policy, without the rest days of article 74 nor the company's.
it('counts the working days of a range, both ends included', function (string $from, string $to, WorkWeek $week, array $extra, int $days): void {
    expect(WorkingDays::count(day($from), day($to), $week, [...StatutoryHolidays::for(2026), ...$extra]))->toBe($days);
})->with([
    'two weeks Monday to Friday, with the third Monday of November' => ['2026-11-09', '2026-11-20', WorkWeek::MonFri, [], 9],
    'two weeks Monday to Saturday' => ['2026-11-09', '2026-11-21', WorkWeek::MonSat, [], 11],
    'a company holiday on top' => ['2026-11-09', '2026-11-20', WorkWeek::MonFri, ['2026-11-12'], 8],
    'a weekend in a Monday to Friday week' => ['2026-11-14', '2026-11-15', WorkWeek::MonFri, [], 0],
    'a Saturday in a Monday to Saturday week' => ['2026-11-14', '2026-11-15', WorkWeek::MonSat, [], 1],
    'a single working day' => ['2026-11-10', '2026-11-10', WorkWeek::MonFri, [], 1],
    'a single holiday' => ['2026-11-16', '2026-11-16', WorkWeek::MonFri, [], 0],
    'a holiday on a weekend is not taken twice' => ['2026-11-13', '2026-11-16', WorkWeek::MonFri, ['2026-11-14'], 1],
]);

it('ignores the hour of the dates it is given', function (): void {
    expect(WorkingDays::count(day('2026-11-10 23:00:00'), day('2026-11-11 01:00:00'), WorkWeek::MonFri, []))->toBe(2);
});

it('refuses a range that ends before it starts', function (): void {
    expect(fn () => WorkingDays::count(day('2026-11-11'), day('2026-11-10'), WorkWeek::MonFri, []))->toThrow(InvalidArgumentException::class);
});

it('finds the working day that comes a number of working days after another', function (string $from, int $days, WorkWeek $week, string $expected): void {
    expect(WorkingDays::add(day($from), $days, $week, StatutoryHolidays::for(2026))->toDateString())->toBe($expected);
})->with([
    'three after a Friday, over a weekend and a holiday' => ['2026-11-13', 3, WorkWeek::MonFri, '2026-11-19'],
    'one after a Thursday' => ['2026-11-12', 1, WorkWeek::MonFri, '2026-11-13'],
    'one after a Friday counting Saturdays' => ['2026-11-13', 1, WorkWeek::MonSat, '2026-11-14'],
    'starting on a day that is not a working day' => ['2026-11-14', 1, WorkWeek::MonFri, '2026-11-17'],
    'none is the same day' => ['2026-11-13', 0, WorkWeek::MonFri, '2026-11-13'],
]);

it('refuses to go back in working days', function (): void {
    expect(fn () => WorkingDays::add(day('2026-11-13'), -1, WorkWeek::MonFri, []))->toThrow(InvalidArgumentException::class);
});
