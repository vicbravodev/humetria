<?php

declare(strict_types=1);

use App\Modules\Leave\Support\ProportionalDays;
use Carbon\CarbonImmutable;

mutates(ProportionalDays::class);

// Criterio C-07: days of the year in course × days elapsed / 365, two decimals, never more than the year gives.
it('gives the part of the year in course that was worked, in days', function (string $start, string $end, int $days, float $expected): void {
    expect(ProportionalDays::for(CarbonImmutable::parse($start), CarbonImmutable::parse($end), $days))->toBe($expected);
})->with([
    'half a year of 12 days' => ['2025-01-01', '2025-07-02', 12, 5.98],
    'the same day it started' => ['2025-01-01', '2025-01-01', 12, 0.0],
    'one day' => ['2025-01-01', '2025-01-02', 12, 0.03],
    'one day short of the year' => ['2025-01-01', '2025-12-31', 12, 11.97],
    'the whole year' => ['2025-01-01', '2026-01-01', 12, 12.0],
    'a leap year stops at what the year gives' => ['2024-01-01', '2024-12-31', 12, 12.0],
    '400 days stop at what the year gives' => ['2025-01-01', '2026-02-05', 12, 12.0],
    'a year of 22 days' => ['2025-03-15', '2025-09-15', 22, 11.09],
    // The day of the baja is not counted: from 15 March to 14 July are 121 days elapsed, and to 15 July, 122 (criterio C-07).
    'the day of the baja is not counted: 121 days' => ['2026-03-15', '2026-07-14', 22, 7.29],
    'one more day, 122' => ['2026-03-15', '2026-07-15', 22, 7.35],
    'with the hours dropped' => ['2025-01-01 22:00:00', '2025-07-02 03:00:00', 12, 5.98],
]);

it('refuses an end before the start, and days below zero', function (): void {
    expect(fn () => ProportionalDays::for(CarbonImmutable::parse('2025-01-02'), CarbonImmutable::parse('2025-01-01'), 12))->toThrow(InvalidArgumentException::class)
        ->and(fn () => ProportionalDays::for(CarbonImmutable::parse('2025-01-01'), CarbonImmutable::parse('2025-07-02'), -1))->toThrow(InvalidArgumentException::class)
        ->and(ProportionalDays::for(CarbonImmutable::parse('2025-01-01'), CarbonImmutable::parse('2025-07-02'), 0))->toBe(0.0);
});
