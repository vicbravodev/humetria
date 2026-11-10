<?php

declare(strict_types=1);

use App\Modules\Engagement\Enums\Driver;
use App\Modules\Engagement\Support\Bank\ItemBank;
use App\Modules\Engagement\Support\Results\DriverResult;
use App\Modules\Engagement\Support\Results\DriverScores;
use App\Modules\Engagement\Support\Results\ItemResult;

mutates(DriverScores::class);

/** A shown agreement result with `$favorable` percent favorable, or a hidden one when null. */
function driverScoresResult(string $key, ?int $favorable): ItemResult
{
    return $favorable === null
        ? new ItemResult($key, false, null, null, null, null, null)
        : new ItemResult($key, true, 40, $favorable, 0, 100 - $favorable, [0, 0, 0, 0, 0]);
}

/**
 * Every company question at 50, with the overrides of `$favorable` (key => percent or null for hidden).
 *
 * @param  array<string, int|null>  $favorable
 * @return array<string, ItemResult>
 */
function driverScoresByKey(array $favorable = []): array
{
    $byKey = [];
    foreach (Driver::cases() as $driver) {
        foreach (ItemBank::ofDriver($driver) as $item) {
            $byKey[$item->key] = driverScoresResult($item->key, array_key_exists($item->key, $favorable) ? $favorable[$item->key] : 50);
        }
    }

    return $byKey;
}

/**
 * @param  array<string, ItemResult>  $byKey
 * @return array<string, DriverResult>
 */
function driverScoresByDriver(array $byKey): array
{
    $byDriver = [];
    foreach (DriverScores::of($byKey) as $result) {
        $byDriver[$result->driver->value] = $result;
    }

    return $byDriver;
}

it('scores a driver of three questions as the average of their favorable share', function (): void {
    $scores = driverScoresByDriver(driverScoresByKey(['clarity.goals' => 80, 'clarity.contribution' => 70, 'clarity.priorities' => 60]));

    expect($scores['clarity']->favorable)->toBe(70)->and($scores['clarity']->descriptive)->toBeFalse()->and($scores['clarity']->items)->toHaveCount(3);
});

it('rounds the average to the nearest point, not down', function (): void {
    $scores = driverScoresByDriver(driverScoresByKey(['clarity.goals' => 80, 'clarity.contribution' => 71, 'clarity.priorities' => 60]));

    expect($scores['clarity']->favorable)->toBe(70);

    $scores = driverScoresByDriver(driverScoresByKey(['clarity.goals' => 80, 'clarity.contribution' => 72, 'clarity.priorities' => 61]));

    expect($scores['clarity']->favorable)->toBe(71);
});

it('does not give a number to a driver of two questions: it is reported question by question', function (): void {
    $scores = driverScoresByDriver(driverScoresByKey());

    expect($scores['recognition']->descriptive)->toBeTrue()->and($scores['recognition']->favorable)->toBeNull()->and($scores['recognition']->items)->toHaveCount(2);
});

it('leaves a driver without number when one of its questions is hidden, but keeps the shown ones', function (): void {
    $scores = driverScoresByDriver(driverScoresByKey(['clarity.goals' => null]));

    expect($scores['clarity']->favorable)->toBeNull()->and($scores['clarity']->items)->toHaveCount(3)
        ->and($scores['clarity']->items[0]->shown)->toBeFalse()->and($scores['leadership']->favorable)->toBe(50);
});

it('leaves a driver without number when one of its questions is missing', function (): void {
    $byKey = driverScoresByKey();
    unset($byKey['clarity.goals']);

    $scores = driverScoresByDriver($byKey);

    expect($scores['clarity']->favorable)->toBeNull()->and($scores['clarity']->items)->toHaveCount(2);
});

it('lists the ten drivers in the order of the bank', function (): void {
    expect(array_map(fn ($result): string => $result->driver->value, DriverScores::of(driverScoresByKey())))->toBe(array_map(fn (Driver $driver): string => $driver->value, Driver::cases()));
});
