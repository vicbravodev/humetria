<?php

declare(strict_types=1);

use App\Modules\Compensation\Data\FiscalSnapshot;
use App\Modules\Compensation\Support\Calculation\Rate;
use App\Modules\Compensation\Support\Calculation\TenureYears;
use Carbon\CarbonImmutable;

mutates(TenureYears::class);

function years(string $hiredOn, string $on): int
{
    return TenureYears::at(CarbonImmutable::parse($hiredOn), CarbonImmutable::parse($on));
}

/** The LFT art. 76 table, as the platform's parameters hold it (one row per step). */
function vacationTable(): FiscalSnapshot
{
    $steps = [1 => 12, 2 => 14, 3 => 16, 4 => 18, 5 => 20, 6 => 22, 11 => 24, 16 => 26, 21 => 28, 26 => 30, 31 => 32];

    return new FiscalSnapshot(array_map(fn (int $days): Rate => Rate::scaled($days, 0), array_combine(
        array_map(fn (int $year): string => "vacation_days.{$year}", array_keys($steps)),
        $steps,
    )), []);
}

it('counts whole years on the exact day of the anniversary', function (): void {
    expect(years('2020-03-15', '2026-03-14'))->toBe(5)
        ->and(years('2020-03-15', '2026-03-15'))->toBe(6)
        ->and(years('2020-03-15', '2026-03-16'))->toBe(6)
        ->and(years('2020-03-15', '2026-12-31'))->toBe(6)
        ->and(years('2020-03-15', '2027-03-14'))->toBe(6)
        ->and(years('2020-03-15', '2027-01-01'))->toBe(6)
        ->and(years('2020-12-31', '2021-12-30'))->toBe(0)
        ->and(years('2020-12-31', '2021-12-31'))->toBe(1)
        ->and(years('2020-01-01', '2020-12-31'))->toBe(0)
        ->and(years('2020-01-01', '2021-01-01'))->toBe(1);
});

it('has zero years on the day of hire, on the days before the first anniversary and for a hire after the date', function (): void {
    expect(years('2026-06-30', '2026-06-30'))->toBe(0)
        ->and(years('2026-06-30', '2027-06-29'))->toBe(0)
        ->and(years('2026-06-30', '2027-06-30'))->toBe(1)
        ->and(years('2026-06-30', '2026-06-29'))->toBe(0)
        ->and(years('2026-06-30', '2026-01-01'))->toBe(0)
        ->and(years('2026-06-30', '2020-06-30'))->toBe(0)
        ->and(years('2027-01-01', '2026-12-31'))->toBe(0);
});

it('ignores the time of day', function (): void {
    expect(TenureYears::at(CarbonImmutable::parse('2020-03-15 18:00:00'), CarbonImmutable::parse('2026-03-15 00:00:00')))->toBe(6)
        ->and(TenureYears::at(CarbonImmutable::parse('2020-03-15 00:00:00'), CarbonImmutable::parse('2026-03-14 23:59:59')))->toBe(5);
});

// Convention for a hire on Feb 29 (the LFT does not say): in a year without Feb 29 the anniversary is Feb 28, the
// earlier day, which is the one that favours the worker (LFT art. 18, in dubio pro operario). In a leap year it is Feb 29.
it('completes the year of someone hired on Feb 29 on Feb 28 in a year without Feb 29, and on Feb 29 in a leap year', function (): void {
    expect(years('2020-02-29', '2021-02-27'))->toBe(0)
        ->and(years('2020-02-29', '2021-02-28'))->toBe(1)
        ->and(years('2020-02-29', '2021-03-01'))->toBe(1)
        ->and(years('2020-02-29', '2022-02-27'))->toBe(1)
        ->and(years('2020-02-29', '2022-02-28'))->toBe(2)
        ->and(years('2020-02-29', '2024-02-28'))->toBe(3)
        ->and(years('2020-02-29', '2024-02-29'))->toBe(4)
        ->and(years('2020-02-29', '2024-03-01'))->toBe(4)
        ->and(years('2020-02-29', '2025-02-27'))->toBe(4)
        ->and(years('2020-02-29', '2025-02-28'))->toBe(5);
});

it('counts a scenario on Feb 29 for someone hired on another day', function (): void {
    expect(years('2020-02-28', '2024-02-29'))->toBe(4)
        ->and(years('2020-03-01', '2024-02-29'))->toBe(3)
        ->and(years('2024-02-29', '2024-02-29'))->toBe(0);
});

it('steps vacation days on the exact anniversary, for a person hired on Feb 29 and on the scenario date, and with 0 years', function (): void {
    $days = fn (string $hiredOn, string $on): int => vacationTable()->legalVacationDays(years($hiredOn, $on));

    expect($days('2020-03-15', '2025-03-14'))->toBe(18)  // 4 years until the day before the anniversary
        ->and($days('2020-03-15', '2025-03-15'))->toBe(20)  // 5 years on the day itself
        ->and($days('2020-03-15', '2026-03-15'))->toBe(22)  // 6 years: the 6-10 step
        ->and($days('2020-03-15', '2031-03-14'))->toBe(22)
        ->and($days('2020-03-15', '2031-03-15'))->toBe(24)  // 11 years: the next step
        ->and($days('2020-02-29', '2021-02-27'))->toBe(0)   // Feb 29 hire: not yet one year
        ->and($days('2020-02-29', '2021-02-28'))->toBe(12)  // the anniversary is Feb 28
        ->and($days('2020-02-29', '2024-02-28'))->toBe(16)
        ->and($days('2020-02-29', '2024-02-29'))->toBe(18)
        ->and($days('2026-06-30', '2026-06-30'))->toBe(0)   // hired on the scenario date: 0 years, 0 days
        ->and($days('2026-06-30', '2027-06-29'))->toBe(0)
        ->and($days('2026-06-30', '2027-06-30'))->toBe(12);
});
