<?php

declare(strict_types=1);

use App\Modules\Demos\Contracts\DemoWorkspace;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\DemoClock;
use Database\Seeders\Demo\DemoPerson;
use Database\Seeders\Demo\DemoRoster;
use Database\Seeders\Demo\DemoWorkspaceRunner;

it('reserves about 40 rank-and-file people for the door, in every center', function (): void {
    $people = DemoRoster::people();
    $managers = array_flip(array_filter(array_map(fn (DemoPerson $person): ?string => $person->reportsTo, $people)));
    $reserved = array_filter($people, fn (DemoPerson $person): bool => $person->reservedForDoor);
    $members = [DemoRoster::OWNER, DemoRoster::HR];

    expect(count($reserved))->toBeGreaterThanOrEqual(38)->toBeLessThanOrEqual(42)
        ->and(array_unique(array_map(fn (DemoPerson $person): string => $person->centerKey, $reserved)))->toHaveCount(4);

    foreach ($reserved as $person) {
        expect(isset($managers[$person->key]))->toBeFalse()
            ->and(in_array($person->key, $members, true))->toBeFalse();
    }
});

it('restores the clock even when the callback fails', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-16 18:00:00', 'UTC'));

    // 09:00 in Mexico City is 15:00 UTC: the country has had no daylight saving since 2022.
    expect(DemoClock::at('-180 days', fn (): string => now()->utc()->toIso8601String()))->toBe('2026-05-20T15:00:00+00:00')
        ->and(now()->utc()->toIso8601String())->toBe('2026-11-16T18:00:00+00:00')
        ->and(fn (): mixed => DemoClock::at('-3 days', fn (): never => throw new RuntimeException('falló')))->toThrow(RuntimeException::class, 'falló')
        ->and(now()->utc()->toIso8601String())->toBe('2026-11-16T18:00:00+00:00');
});

it('counts the days from today in Mexico City, not in UTC', function (): void {
    // 03:00 UTC on the 17th is still the evening of the 16th in Mexico City.
    $this->travelTo(CarbonImmutable::parse('2026-11-17 03:00:00', 'UTC'));

    expect(DemoClock::at('-1 day', fn (): string => now()->utc()->toIso8601String()))->toBe('2026-11-15T15:00:00+00:00');
});

it('tells the story at any minute of the day, past midnight too', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-16 18:00:00', 'UTC'));

    // A night shift: in at 22:00, out at 07:00 of the next day (31 h after the day's midnight); 06:00 is 12:00 UTC.
    expect(DemoClock::at('-2 days', fn (): string => now()->utc()->toIso8601String(), 22 * 60))->toBe('2026-11-15T04:00:00+00:00')
        ->and(DemoClock::at('-2 days', fn (): string => now()->utc()->toIso8601String(), 31 * 60))->toBe('2026-11-15T13:00:00+00:00')
        ->and(DemoClock::at('-2 days', fn (): string => now()->utc()->toIso8601String(), 6 * 60 + 7))->toBe('2026-11-14T12:07:00+00:00');
});

it('never stamps anything after the moment the story is told, when it is told before 09:00', function (): void {
    // 07:30 in Mexico City: «today at 09:00» has not happened yet.
    $this->travelTo(CarbonImmutable::parse('2026-11-16 13:30:00', 'UTC'));

    expect(DemoClock::at('0 days', fn (): string => now()->utc()->toIso8601String()))->toBe('2026-11-16T13:30:00+00:00')
        ->and(DemoClock::at('-1 day', fn (): string => now()->utc()->toIso8601String()))->toBe('2026-11-15T15:00:00+00:00')
        ->and(DemoClock::at('-1 day', fn (): string => now()->utc()->toIso8601String(), 31 * 60))->toBe('2026-11-16T13:00:00+00:00')
        // Inside another part told on a past day, the bound is still the moment the story is told, not that day.
        ->and(DemoClock::at('-10 days', fn (): string => DemoClock::at('+12 days', fn (): string => now()->utc()->toIso8601String())))->toBe('2026-11-16T13:30:00+00:00')
        ->and(now()->utc()->toIso8601String())->toBe('2026-11-16T13:30:00+00:00');
});

it('is the workspace the console prepares', function (): void {
    expect(resolve(DemoWorkspace::class))->toBeInstanceOf(DemoWorkspaceRunner::class);
});

// Production has neither factories nor Faker: the story is written by hand.
arch('the demo story uses no factory and no Faker')
    ->expect('Database\Seeders\Demo')
    ->not->toUse(['fake', 'Faker\Factory', 'Faker\Generator', 'Database\Factories', 'Illuminate\Database\Eloquent\Factories']);
