<?php

declare(strict_types=1);

use App\Modules\Engagement\Enums\Direction;
use App\Modules\Engagement\Support\Results\ItemResult;
use App\Modules\Engagement\Support\Results\WaveComparison;
use App\Modules\Engagement\Support\Thresholds;
use Tests\TestCase;

uses(TestCase::class);

mutates(WaveComparison::class);

function waveResult(?int $favorable, int $answered = 40): ItemResult
{
    return $favorable === null
        ? new ItemResult('clarity.goals', false, null, null, null, null, null)
        : new ItemResult('clarity.goals', true, $answered, $favorable, 0, 100 - $favorable, [0, 0, 0, 0, 0]);
}

it('says +7 and up when 72 follows 65 and both waves have 30 or more answers', function (): void {
    $delta = WaveComparison::delta(waveResult(72, 40), waveResult(65, 35), true, resolve(Thresholds::class));

    expect($delta?->points)->toBe(7)->and($delta?->direction)->toBe(Direction::Up);
});

it('gives the points but no arrow when either wave has fewer than 30 answers', function (): void {
    $before = WaveComparison::delta(waveResult(72, 40), waveResult(65, 29), true, resolve(Thresholds::class));
    $now = WaveComparison::delta(waveResult(72, 29), waveResult(65, 40), true, resolve(Thresholds::class));

    expect($before?->points)->toBe(7)->and($before?->direction)->toBeNull()
        ->and($now?->points)->toBe(7)->and($now?->direction)->toBeNull();
});

it('gives the arrow at exactly 30 answers in both waves', function (): void {
    expect(WaveComparison::delta(waveResult(72, 30), waveResult(65, 30), true, resolve(Thresholds::class))?->direction)->toBe(Direction::Up);
});

it('says down with negative points and same with none', function (): void {
    $down = WaveComparison::delta(waveResult(60, 40), waveResult(65, 40), true, resolve(Thresholds::class));
    $same = WaveComparison::delta(waveResult(65, 40), waveResult(65, 40), true, resolve(Thresholds::class));

    expect($down?->points)->toBe(-5)->and($down?->direction)->toBe(Direction::Down)
        ->and($same?->points)->toBe(0)->and($same?->direction)->toBe(Direction::Same);
});

it('compares nothing when the wording changed', function (): void {
    expect(WaveComparison::delta(waveResult(72), waveResult(65), false, resolve(Thresholds::class)))->toBeNull();
});

it('compares nothing when either side is missing or hidden', function (): void {
    $thresholds = resolve(Thresholds::class);

    expect(WaveComparison::delta(waveResult(72), waveResult(null), true, $thresholds))->toBeNull()
        ->and(WaveComparison::delta(waveResult(null), waveResult(65), true, $thresholds))->toBeNull()
        ->and(WaveComparison::delta(null, waveResult(65), true, $thresholds))->toBeNull()
        ->and(WaveComparison::delta(waveResult(72), null, true, $thresholds))->toBeNull();
});

it('says how many points a group is from the company, with no arrow', function (): void {
    expect(WaveComparison::againstCompany(waveResult(72), waveResult(65)))->toBe(7)
        ->and(WaveComparison::againstCompany(waveResult(60), waveResult(65)))->toBe(-5)
        ->and(WaveComparison::againstCompany(waveResult(65), waveResult(65)))->toBe(0);
});

it('says nothing against the company when either is hidden', function (): void {
    expect(WaveComparison::againstCompany(waveResult(null), waveResult(65)))->toBeNull()
        ->and(WaveComparison::againstCompany(waveResult(72), waveResult(null)))->toBeNull();
});
