<?php

declare(strict_types=1);

use App\Modules\Attendance\Support\WeekPeriod;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

mutates(WeekPeriod::class);

beforeEach(fn () => config(['app.display_timezone' => 'America/Mexico_City']));

it('puts a Sunday in the week that started the Monday before', function (): void {
    $week = WeekPeriod::containing(CarbonImmutable::parse('2026-11-08 12:00', 'America/Mexico_City'));

    expect($week->start()->format('Y-m-d H:i:s.u e'))->toBe('2026-11-02 00:00:00.000000 America/Mexico_City')
        ->and($week->end()->format('Y-m-d H:i:s.u e'))->toBe('2026-11-08 23:59:59.999999 America/Mexico_City');
});

it('reads the week in the local time, not in UTC', function (string $utc, string $monday): void {
    expect(WeekPeriod::containing(CarbonImmutable::parse($utc, 'UTC'))->start()->toDateString())->toBe($monday);
})->with([
    // Sunday 23:59 in Mexico City is already Monday in UTC.
    'last minute of Sunday' => ['2026-11-09 05:59:00', '2026-11-02'],
    'first minute of Monday' => ['2026-11-09 06:00:00', '2026-11-09'],
    'a Monday itself' => ['2026-11-02 06:00:00', '2026-11-02'],
]);

it('gives the local date of an instant', function (): void {
    $week = WeekPeriod::containing(CarbonImmutable::parse('2026-11-04 12:00', 'UTC'));

    expect($week->localDate(CarbonImmutable::parse('2026-11-09 05:59:00', 'UTC'))->format('Y-m-d H:i e'))->toBe('2026-11-08 00:00 America/Mexico_City');
});

it('gives its bounds as UTC instants for queries', function (): void {
    $week = WeekPeriod::containing(CarbonImmutable::parse('2026-11-04 12:00', 'UTC'));

    expect($week->startUtc()->format('Y-m-d H:i:s e'))->toBe('2026-11-02 06:00:00 UTC')
        ->and($week->nextStartUtc()->format('Y-m-d H:i:s e'))->toBe('2026-11-09 06:00:00 UTC');
});

it('takes the week of a calendar date as a local date, whatever zone its Carbon carries', function (string $date): void {
    // Monday 9 November at midnight UTC is still Sunday the 8th in Mexico City; the date is what counts.
    expect(WeekPeriod::ofDate(CarbonImmutable::parse($date, 'UTC'))->start()->format('Y-m-d e'))->toBe('2026-11-09 America/Mexico_City');
})->with(['Monday' => ['2026-11-09'], 'Sunday' => ['2026-11-15 23:00']]);

it('is over only once its Sunday ended, local', function (): void {
    $week = WeekPeriod::ofDate(CarbonImmutable::parse('2026-11-02'));

    $this->travelTo(CarbonImmutable::parse('2026-11-08 23:59:59.999999', 'America/Mexico_City'));
    expect($week->isOver())->toBeFalse();

    $this->travelTo(CarbonImmutable::parse('2026-11-09 00:00:00', 'America/Mexico_City'));
    expect($week->isOver())->toBeTrue();
});
