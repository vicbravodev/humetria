<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\ShiftType;
use App\Modules\Attendance\Support\LegalLimits;

mutates(LegalLimits::class);

// Criterio 2.1: art. 59 and transitorio segundo, 48 h in 2026 down to 40 h in 2030.
it('gives the weekly ordinary maximum of each year of the reform', function (int $year, int $minutes): void {
    expect(LegalLimits::weeklyOrdinaryMinutes($year))->toBe($minutes);
})->with([
    [2026, 2880],
    [2027, 2760],
    [2028, 2640],
    [2029, 2520],
    [2030, 2400],
]);

// Criterio 2.3: transitorio cuarto, 9 h in 2026 and 2027 up to 12 h in 2030.
it('gives the weekly overtime cap of each year of the reform', function (int $year, int $minutes): void {
    expect(LegalLimits::weeklyOvertimeCapMinutes($year))->toBe($minutes);
})->with([
    [2026, 540],
    [2027, 540],
    [2028, 600],
    [2029, 660],
    [2030, 720],
]);

it('uses 2026 before the reform and 2030 after it ends', function (): void {
    expect(LegalLimits::weeklyOrdinaryMinutes(2025))->toBe(2880)
        ->and(LegalLimits::weeklyOvertimeCapMinutes(2025))->toBe(540)
        ->and(LegalLimits::weeklyOrdinaryMinutes(2031))->toBe(2400)
        ->and(LegalLimits::weeklyOvertimeCapMinutes(2031))->toBe(720);
});

// Criterio 2.2: art. 61.
it('gives the daily ordinary maximum of each shift', function (ShiftType $shift, int $minutes): void {
    expect(LegalLimits::dailyOrdinaryMinutes($shift))->toBe($minutes);
})->with([
    [ShiftType::Day, 480],
    [ShiftType::Night, 420],
    [ShiftType::Mixed, 450],
]);

// Criterios 2.3 and 2.4: arts. 66 and 68.
it('keeps the fixed limits of arts. 66 and 68', function (): void {
    expect(LegalLimits::DAILY_OVERTIME_MAX)->toBe(240)
        ->and(LegalLimits::OVERTIME_DAYS_MAX)->toBe(4)
        ->and(LegalLimits::DAY_TOTAL_MAX)->toBe(720)
        ->and(LegalLimits::TRIPLE_WEEKLY_MAX)->toBe(240);
});
