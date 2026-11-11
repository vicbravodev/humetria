<?php

declare(strict_types=1);

use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Support\Results\ItemResult;
use App\Modules\Engagement\Support\Results\RecommendationResult;
use App\Modules\Engagement\Support\Results\Trend;
use App\Modules\Engagement\Support\Results\TrendPoint;
use App\Modules\Engagement\Support\Results\Wave;
use App\Modules\Engagement\Support\Thresholds;

mutates(Trend::class, TrendPoint::class);

const TREND_FIXED = ['eng.pride@1', 'eng.motivation@1', 'eng.connection@1', 'eng.recommend@1'];

function trendItem(string $key, ?int $favorable, int $answered = 40): ItemResult
{
    return $favorable === null
        ? new ItemResult($key, false, null, null, null, null, null)
        : new ItemResult($key, true, $answered, $favorable, 0, 100 - $favorable, null);
}

/**
 * A wave of the company. `$items` are the keys (and their results) the wave asked, over the fixed ones.
 *
 * @param  array<string, ItemResult>  $byKey
 * @param  list<string>  $refs
 */
function trendWave(string $id, string $label, array $byKey = [], array $refs = TREND_FIXED, ?int $recommendation = null): Wave
{
    return new Wave($id, SurveyKind::Pulse, $label, $refs, $byKey, new RecommendationResult($recommendation !== null, $recommendation, null));
}

/** @return list<array{string, int, string|null}> */
function trendShape(array $points): array
{
    return array_map(fn (TrendPoint $point): array => [$point->label, $point->value, $point->direction?->value], $points);
}

/** @param  list<int|null>  $values */
function trendWaves(array $values, string $key = 'clarity.contribution', string $ref = 'clarity.contribution@1', int $answered = 40): array
{
    $waves = [];

    foreach ($values as $position => $value) {
        $waves[] = trendWave("w{$position}", "mes {$position}", [$key => trendItem($key, $value, $answered)], [...TREND_FIXED, $ref]);
    }

    return $waves;
}

beforeEach(function (): void {
    $this->thresholds = resolve(Thresholds::class);
});

it('draws a question across waves, with the arrow between each point and the one before', function (): void {
    $points = Trend::item(trendWaves([60, 66, 66]), 'clarity.contribution', $this->thresholds);

    expect(trendShape($points))->toBe([['mes 0', 60, null], ['mes 1', 66, 'up'], ['mes 2', 66, 'same']]);
});

it('points down when it fell', function (): void {
    expect(trendShape(Trend::item(trendWaves([70, 64]), 'clarity.contribution', $this->thresholds)))->toBe([['mes 0', 70, null], ['mes 1', 64, 'down']]);
});

it('gives the point without arrow when either wave has fewer than thirty answers', function (): void {
    $waves = trendWaves([60, 66, 66]);
    $waves[1] = trendWave('w1', 'mes 1', ['clarity.contribution' => trendItem('clarity.contribution', 66, 29)], [...TREND_FIXED, 'clarity.contribution@1']);

    expect(trendShape(Trend::item($waves, 'clarity.contribution', $this->thresholds)))->toBe([['mes 0', 60, null], ['mes 1', 66, null], ['mes 2', 66, null]]);
});

it('gives the arrow with exactly thirty answers in both waves', function (): void {
    expect(trendShape(Trend::item(trendWaves([60, 66], answered: 30), 'clarity.contribution', $this->thresholds)))->toBe([['mes 0', 60, null], ['mes 1', 66, 'up']]);
});

it('skips a wave that did not ask the question and compares the two that did', function (): void {
    $waves = trendWaves([60, 66, 70]);
    $waves[1] = trendWave('w1', 'mes 1');

    expect(trendShape(Trend::item($waves, 'clarity.contribution', $this->thresholds)))->toBe([['mes 0', 60, null], ['mes 2', 70, 'up']]);
});

it('starts again at the wave where the wording changed', function (): void {
    $waves = [...trendWaves([60, 66]), trendWave('w2', 'mes 2', ['clarity.contribution' => trendItem('clarity.contribution', 80)], [...TREND_FIXED, 'clarity.contribution@2'])];

    expect(trendShape(Trend::item($waves, 'clarity.contribution', $this->thresholds)))->toBe([['mes 2', 80, null]]);
});

it('leaves out a wave where the question was hidden', function (): void {
    $waves = trendWaves([60, null, 66]);

    expect(trendShape(Trend::item($waves, 'clarity.contribution', $this->thresholds)))->toBe([['mes 0', 60, null], ['mes 2', 66, 'up']]);
});

it('has a single point without arrow for a single wave, and nothing for none', function (): void {
    expect(trendShape(Trend::item(trendWaves([60]), 'clarity.contribution', $this->thresholds)))->toBe([['mes 0', 60, null]])
        ->and(Trend::item([], 'clarity.contribution', $this->thresholds))->toBe([])
        ->and(Trend::item(trendWaves([60]), 'growth.apply', $this->thresholds))->toBe([]);
});

it('keeps the twelve most recent waves', function (): void {
    $points = Trend::item(trendWaves(range(50, 63)), 'clarity.contribution', $this->thresholds);

    expect($points)->toHaveCount(12)
        ->and($points[0]->label)->toBe('mes 2')
        ->and($points[0]->direction)->toBeNull()
        ->and($points[11]->label)->toBe('mes 13');
});

/** @param  array<string, int|null>  $favorable */
function trendIndexWave(string $id, array $favorable, array $answered = [], array $refs = TREND_FIXED): Wave
{
    $byKey = [];

    foreach (['eng.pride', 'eng.motivation', 'eng.connection'] as $key) {
        $byKey[$key] = trendItem($key, $favorable[$key] ?? null, $answered[$key] ?? 40);
    }

    return trendWave($id, "mes {$id}", $byKey, $refs);
}

it('draws the engagement index with the arrow from the smallest of its three questions', function (): void {
    $even = fn (int $value): array => ['eng.pride' => $value, 'eng.motivation' => $value, 'eng.connection' => $value];

    $points = Trend::index([trendIndexWave('0', $even(60)), trendIndexWave('1', $even(70)), trendIndexWave('2', $even(70), ['eng.motivation' => 29])], $this->thresholds);

    expect(trendShape($points))->toBe([['mes 0', 60, null], ['mes 1', 70, 'up'], ['mes 2', 70, null]]);
});

it('averages the three questions of the index', function (): void {
    $points = Trend::index([trendIndexWave('0', ['eng.pride' => 60, 'eng.motivation' => 70, 'eng.connection' => 80])], $this->thresholds);

    expect($points[0]->value)->toBe(70);
});

it('leaves a wave out of the index where a question of it was hidden or reworded', function (): void {
    $even = ['eng.pride' => 60, 'eng.motivation' => 60, 'eng.connection' => 60];
    $reworded = [...TREND_FIXED];
    $reworded[0] = 'eng.pride@2';

    $waves = [trendIndexWave('0', $even), trendIndexWave('1', [...$even, 'eng.pride' => null]), trendIndexWave('2', $even, refs: $reworded), trendIndexWave('3', $even)];

    expect(trendShape(Trend::index($waves, $this->thresholds)))->toBe([['mes 0', 60, null], ['mes 3', 60, 'same']]);
});

it('starts the index again at the latest wording of its questions', function (): void {
    $even = ['eng.pride' => 60, 'eng.motivation' => 60, 'eng.connection' => 60];
    $reworded = ['eng.pride@2', 'eng.motivation@1', 'eng.connection@1', 'eng.recommend@1'];

    $waves = [trendIndexWave('0', $even), trendIndexWave('1', $even, refs: $reworded)];

    expect(trendShape(Trend::index($waves, $this->thresholds)))->toBe([['mes 1', 60, null]]);
});

it('has no index series without waves', function (): void {
    expect(Trend::index([], $this->thresholds))->toBe([]);
});

it('draws the recommendation index only for waves that show it', function (): void {
    $waves = [trendWave('0', 'mes 0', recommendation: 20), trendWave('1', 'mes 1', recommendation: null), trendWave('2', 'mes 2', recommendation: 35), trendWave('3', 'mes 3', recommendation: -5)];

    expect(trendShape(Trend::recommendation($waves)))->toBe([['mes 0', 20, null], ['mes 2', 35, 'up'], ['mes 3', -5, 'down']]);
});

it('does not compare the recommendation of waves that asked it with another wording', function (): void {
    $reworded = ['eng.pride@1', 'eng.motivation@1', 'eng.connection@1', 'eng.recommend@2'];
    $waves = [trendWave('0', 'mes 0', recommendation: 20), trendWave('1', 'mes 1', refs: $reworded, recommendation: 35)];

    expect(trendShape(Trend::recommendation($waves)))->toBe([['mes 1', 35, null]]);
});

it('has a recommendation point without arrow for a single wave', function (): void {
    expect(Trend::recommendation([trendWave('0', 'mes 0', recommendation: 20)])[0]->direction)->toBeNull()
        ->and(Trend::recommendation([]))->toBe([]);
});
