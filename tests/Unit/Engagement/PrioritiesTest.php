<?php

declare(strict_types=1);

use App\Modules\Engagement\Enums\Driver;
use App\Modules\Engagement\Enums\Priority;
use App\Modules\Engagement\Support\Audience\Segment;
use App\Modules\Engagement\Support\Bank\ItemBank;
use App\Modules\Engagement\Support\Results\ItemResult;
use App\Modules\Engagement\Support\Results\Priorities;
use App\Modules\Engagement\Support\Results\ResponseRow;
use App\Modules\Engagement\Support\Thresholds;
use Tests\TestCase;

uses(TestCase::class);

mutates(Priorities::class, Priority::class);

/** The level of person `$i`: 1 to 5, in turns. Every engagement question of the person is answered with it. */
function prioritiesLevel(int $i): int
{
    return 1 + $i % 5;
}

/**
 * `$count` people. Each driver in `$answers` answers every one of its questions with `$answers[driver]($i)` (`null` =
 * omitted); a driver that is not there answers 3 for everybody (no relation with the engagement).
 *
 * @param  array<string, Closure(int): ?int>  $answers
 * @return list<ResponseRow>
 */
function prioritiesRows(int $count, array $answers): array
{
    $rows = [];
    for ($i = 0; $i < $count; $i++) {
        $values = array_fill_keys(ItemBank::INDEX, prioritiesLevel($i));
        foreach (Driver::cases() as $driver) {
            $value = isset($answers[$driver->value]) ? $answers[$driver->value]($i) : 3;
            foreach (ItemBank::ofDriver($driver) as $item) {
                $values[$item->key] = $value;
            }
        }
        $rows[] = new ResponseRow(new Segment(null, null, null, null), $values);
    }

    return $rows;
}

/**
 * Every company question shown, at 50 percent favorable unless the driver is in `$favorable`.
 *
 * @param  array<string, int>  $favorable  driver => percent
 * @return array<string, ItemResult>
 */
function prioritiesByKey(array $favorable = []): array
{
    $byKey = [];
    foreach (Driver::cases() as $driver) {
        foreach (ItemBank::ofDriver($driver) as $item) {
            $value = $favorable[$driver->value] ?? 50;
            $byKey[$item->key] = new ItemResult($item->key, true, 40, $value, 0, 100 - $value, [0, 0, 0, 0, 0]);
        }
    }

    return $byKey;
}

/** @return Closure(int): int */
function prioritiesFollows(): Closure
{
    return prioritiesLevel(...);
}

/** Follows the engagement except for the first `$flattened` people with a level other than 3, who answer 3. */
function prioritiesFlattened(int $flattened): Closure
{
    return function (int $i) use ($flattened): int {
        $seen = 0;
        for ($j = 0; $j <= $i; $j++) {
            if (prioritiesLevel($j) !== 3) {
                $seen++;
            }
        }

        return prioritiesLevel($i) !== 3 && $seen <= $flattened ? 3 : prioritiesLevel($i);
    };
}

it('marks High a driver that follows the engagement and comes out low, and Medium one that follows it and comes out high', function (): void {
    $rows = prioritiesRows(40, ['recognition' => prioritiesFollows(), 'leadership' => prioritiesFlattened(6)]);

    $priorities = Priorities::compute($rows, prioritiesByKey(['recognition' => 30, 'leadership' => 80]), resolve(Thresholds::class));

    expect($priorities)->toBe([['driver' => 'recognition', 'level' => 'high'], ['driver' => 'leadership', 'level' => 'medium']]);
});

it('leaves out a driver with no relation to the engagement', function (): void {
    $priorities = Priorities::compute(prioritiesRows(40, ['recognition' => prioritiesFollows()]), prioritiesByKey(['recognition' => 30]), resolve(Thresholds::class));

    expect(array_column($priorities, 'driver'))->toBe(['recognition']);
});

it('leaves out a driver that moves against the engagement', function (): void {
    $rows = prioritiesRows(40, ['recognition' => fn (int $i): int => 6 - prioritiesLevel($i), 'leadership' => prioritiesFollows()]);

    expect(array_column(Priorities::compute($rows, prioritiesByKey(), resolve(Thresholds::class)), 'driver'))->toBe(['leadership']);
});

it('calls Medium a driver whose favorable share equals the median of all the drivers', function (): void {
    $priorities = Priorities::compute(prioritiesRows(40, ['recognition' => prioritiesFollows()]), prioritiesByKey(['recognition' => 50]), resolve(Thresholds::class));

    expect($priorities)->toBe([['driver' => 'recognition', 'level' => 'medium']]);
});

it('keeps only the upper half by impact, from the strongest to the weakest', function (): void {
    $rows = prioritiesRows(40, [
        'clarity' => prioritiesFlattened(0), 'leadership' => prioritiesFlattened(3), 'communication' => prioritiesFlattened(6),
        'recognition' => prioritiesFlattened(9), 'growth' => prioritiesFlattened(12), 'autonomy' => prioritiesFlattened(15),
    ]);

    $priorities = Priorities::compute($rows, prioritiesByKey(), resolve(Thresholds::class));

    expect(array_column($priorities, 'driver'))->toBe(['clarity', 'leadership', 'communication', 'recognition', 'growth']);
});

it('computes nothing with 29 people and something with 30', function (): void {
    $answers = ['recognition' => prioritiesFollows()];

    expect(Priorities::compute(prioritiesRows(29, $answers), prioritiesByKey(['recognition' => 30]), resolve(Thresholds::class)))->toBe([])
        ->and(Priorities::compute(prioritiesRows(30, $answers), prioritiesByKey(['recognition' => 30]), resolve(Thresholds::class)))->toBe([['driver' => 'recognition', 'level' => 'high']]);
});

it('counts the people who answered both the driver and the engagement, so 29 of them leave the driver out', function (): void {
    $rows = prioritiesRows(40, [
        'recognition' => fn (int $i): ?int => $i < 29 ? prioritiesLevel($i) : null,
        'leadership' => fn (int $i): ?int => $i < 30 ? prioritiesLevel($i) : null,
    ]);

    expect(array_column(Priorities::compute($rows, prioritiesByKey(), resolve(Thresholds::class)), 'driver'))->toBe(['leadership']);
});

it('computes nothing when fewer than 3 drivers can be calculated', function (): void {
    $omitted = fn (): ?int => null;
    $answers = array_fill_keys(array_map(fn (Driver $driver): string => $driver->value, Driver::cases()), $omitted);
    $answers['recognition'] = prioritiesFollows();
    $answers['leadership'] = prioritiesFollows();

    expect(Priorities::compute(prioritiesRows(40, $answers), prioritiesByKey(), resolve(Thresholds::class)))->toBe([]);

    $answers['clarity'] = prioritiesFollows();

    expect(Priorities::compute(prioritiesRows(40, $answers), prioritiesByKey(), resolve(Thresholds::class)))->not->toBe([]);
});

it('does not break when everybody answers the same: no variance, no priority', function (): void {
    $rows = array_map(
        fn (int $i): ResponseRow => new ResponseRow(new Segment(null, null, null, null), array_fill_keys([...ItemBank::INDEX, ...array_map(fn ($item) => $item->key, array_merge(...array_map(ItemBank::ofDriver(...), Driver::cases())))], 4)),
        range(1, 40),
    );

    expect(Priorities::compute($rows, prioritiesByKey(), resolve(Thresholds::class)))->toBe([]);
});

it('averages the engagement questions the person answered and skips who answered none', function (): void {
    $rows = prioritiesRows(40, ['recognition' => prioritiesFollows()]);
    $rows = array_map(function (ResponseRow $row, int $i): ResponseRow {
        $answers = $row->answers;
        if ($i < 10) {
            $answers['eng.motivation'] = null;
            $answers['eng.connection'] = null;
        }

        return new ResponseRow($row->segment, $answers);
    }, $rows, array_keys($rows));
    $rows[39] = new ResponseRow($rows[39]->segment, [...$rows[39]->answers, 'eng.pride' => null, 'eng.motivation' => null, 'eng.connection' => null]);

    expect(array_column(Priorities::compute($rows, prioritiesByKey(['recognition' => 30]), resolve(Thresholds::class)), 'driver'))->toBe(['recognition']);
});

it('gives only the driver and the level: no coefficient, no decimal', function (): void {
    $priorities = Priorities::compute(prioritiesRows(40, ['recognition' => prioritiesFollows(), 'leadership' => prioritiesFlattened(6)]), prioritiesByKey(['recognition' => 30]), resolve(Thresholds::class));

    expect($priorities)->not->toBe([])
        ->and(array_map(fn (array $priority): array => array_keys($priority), $priorities))->each->toBe(['driver', 'level'])
        ->and(json_encode($priorities))->not->toMatch('/\d/');
});

it('labels the two levels in Spanish', function (): void {
    expect(Priority::High->label())->toBe('Alta')->and(Priority::Medium->label())->toBe('Media')
        ->and(Priority::High->value)->toBe('high')->and(Priority::Medium->value)->toBe('medium');
});
