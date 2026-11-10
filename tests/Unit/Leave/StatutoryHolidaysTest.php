<?php

declare(strict_types=1);

use App\Modules\Leave\Support\StatutoryHolidays;

mutates(StatutoryHolidays::class);

// docs/normas/vacaciones/texto-de-referencia.md, art. 74 (criterio C-04).
it('lists the mandatory rest days of a year, in order', function (): void {
    expect(StatutoryHolidays::for(2026))->toBe(['2026-01-01', '2026-02-02', '2026-03-16', '2026-05-01', '2026-09-16', '2026-11-16', '2026-12-25']);
});

it('moves the three Mondays with the calendar', function (): void {
    expect(StatutoryHolidays::for(2027))->toContain('2027-02-01', '2027-03-15', '2027-11-15')
        // 2021: February starts on a Monday and so does March and November; the first and the third are the 1st and the 15th.
        ->and(StatutoryHolidays::for(2021))->toContain('2021-02-01', '2021-03-15', '2021-11-15')
        // 2025: the months start on a Saturday; the latest a third Monday can fall is the 17th.
        ->and(StatutoryHolidays::for(2025))->toContain('2025-02-03', '2025-03-17', '2025-11-17');
});

it('adds 1 October every six years, when the federal executive changes', function (int $year, bool $transfer): void {
    expect(in_array("{$year}-10-01", StatutoryHolidays::for($year), true))->toBe($transfer)
        ->and(StatutoryHolidays::for($year))->toHaveCount($transfer ? 8 : 7);
})->with([[2024, true], [2030, true], [2036, true], [2018, false], [2012, false], [2026, false], [2025, false], [2029, false], [2031, false]]);

it('keeps the list in order in a year with 1 October', function (): void {
    expect(StatutoryHolidays::for(2030))->toBe(['2030-01-01', '2030-02-04', '2030-03-18', '2030-05-01', '2030-09-16', '2030-10-01', '2030-11-18', '2030-12-25']);
});
