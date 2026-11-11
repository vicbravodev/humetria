<?php

declare(strict_types=1);

use App\Modules\Leave\Support\PeriodWindow;
use Carbon\CarbonImmutable;

mutates(PeriodWindow::class);

/**
 * @param  array<int, CarbonImmutable>  $anniversaries
 * @return array<int, string>
 */
function asDates(array $anniversaries): array
{
    return array_map(fn (CarbonImmutable $day): string => $day->toDateString(), $anniversaries);
}

function on(string $date): CarbonImmutable
{
    return CarbonImmutable::parse($date);
}

it('lists the anniversaries of a range by year of service, both ends included', function (): void {
    expect(asDates(PeriodWindow::anniversaries(on('2024-03-15'), on('2025-03-15'), on('2026-03-15'))))->toBe([1 => '2025-03-15', 2 => '2026-03-15'])
        ->and(asDates(PeriodWindow::anniversaries(on('2024-03-15'), on('2025-03-16'), on('2026-03-14'))))->toBe([])
        ->and(asDates(PeriodWindow::anniversaries(on('2024-03-15'), on('2025-03-16'), on('2027-12-31'))))->toBe([2 => '2026-03-15', 3 => '2027-03-15']);
});

it('has no anniversary on the day of hire nor before the first year', function (): void {
    expect(PeriodWindow::anniversaries(on('2026-03-15'), on('2020-01-01'), on('2026-03-15')))->toBe([])
        ->and(PeriodWindow::anniversaries(on('2026-03-15'), on('2020-01-01'), on('2027-03-14')))->toBe([])
        ->and(asDates(PeriodWindow::anniversaries(on('2026-03-15'), on('2020-01-01'), on('2027-03-15'))))->toBe([1 => '2027-03-15']);
});

// Criterio C-20.
it('gives a 29 February hire its anniversary on the 28th, and on the 29th in a leap year', function (): void {
    expect(asDates(PeriodWindow::anniversaries(on('2024-02-29'), on('2024-02-29'), on('2028-12-31'))))
        ->toBe([1 => '2025-02-28', 2 => '2026-02-28', 3 => '2027-02-28', 4 => '2028-02-29']);
});

it('ignores the hour of the dates it is given', function (): void {
    expect(asDates(PeriodWindow::anniversaries(on('2024-03-15 20:00:00'), on('2025-03-15 23:00:00'), on('2025-03-15 01:00:00'))))->toBe([1 => '2025-03-15']);
});

// Criterios C-09 and C-11.
it('gives a period six months to be enjoyed and eighteen to prescribe', function (string $anniversary, string $enjoyBy, string $prescribesOn): void {
    $window = PeriodWindow::of(on($anniversary));

    expect($window->enjoyBy->toDateString())->toBe($enjoyBy)
        ->and($window->prescribesOn->toDateString())->toBe($prescribesOn);
})->with([
    ['2026-03-15', '2026-09-15', '2027-09-15'],
    'a month too short uses its last day' => ['2026-08-31', '2027-02-28', '2028-02-29'],
    'with the hour of the anniversary dropped' => ['2026-03-15 22:00:00', '2026-09-15', '2027-09-15'],
]);

it('is past its window the day after the sixth month ends', function (): void {
    $window = PeriodWindow::of(on('2026-03-15'));

    expect($window->pastEnjoyWindow(on('2026-09-15')))->toBeFalse()
        ->and($window->pastEnjoyWindow(on('2026-09-15 23:59:59')))->toBeFalse()
        ->and($window->pastEnjoyWindow(on('2026-09-16')))->toBeTrue();
});

it('can prescribe from the day after the eighteenth month ends', function (): void {
    $window = PeriodWindow::of(on('2026-03-15'));

    expect($window->prescribable(on('2027-09-15')))->toBeFalse()
        ->and($window->prescribable(on('2027-09-15 23:59:59')))->toBeFalse()
        ->and($window->prescribable(on('2027-09-16')))->toBeTrue();
});
