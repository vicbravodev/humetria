<?php

declare(strict_types=1);

use App\Modules\Engagement\Support\Audience\Segment;
use App\Modules\Engagement\Support\Bank\ItemBank;
use App\Modules\Engagement\Support\Results\Indices;
use App\Modules\Engagement\Support\Results\ItemResult;
use App\Modules\Engagement\Support\Results\ResponseRow;
use App\Modules\Engagement\Support\Thresholds;
use Tests\TestCase;

uses(TestCase::class);

mutates(Indices::class);

function indicesResult(string $key, ?int $favorable): ItemResult
{
    return $favorable === null
        ? new ItemResult($key, false, null, null, null, null, null)
        : new ItemResult($key, true, 40, $favorable, 0, 100 - $favorable, [0, 0, 0, 0, 0]);
}

/**
 * @param  array<int, int>  $countByScore  how many people gave each 0 to 10 score
 * @return list<ResponseRow>
 */
function indicesRecommendRows(array $countByScore, int $omitted = 0): array
{
    $rows = [];
    foreach ($countByScore as $score => $count) {
        for ($i = 0; $i < $count; $i++) {
            $rows[] = new ResponseRow(new Segment(null, null, null, null), [ItemBank::RECOMMEND => $score]);
        }
    }
    for ($i = 0; $i < $omitted; $i++) {
        $rows[] = new ResponseRow(new Segment(null, null, null, null), [ItemBank::RECOMMEND => null]);
    }

    return $rows;
}

it('averages the favorable share of the three engagement questions', function (): void {
    $byKey = ['eng.pride' => indicesResult('eng.pride', 80), 'eng.motivation' => indicesResult('eng.motivation', 70), 'eng.connection' => indicesResult('eng.connection', 61)];

    expect(Indices::engagement($byKey))->toBe(70);
});

it('rounds the engagement index half up', function (): void {
    $byKey = ['eng.pride' => indicesResult('eng.pride', 80), 'eng.motivation' => indicesResult('eng.motivation', 70), 'eng.connection' => indicesResult('eng.connection', 62)];

    expect(Indices::engagement($byKey))->toBe(71);
});

it('gives no engagement index when one of its questions is hidden or missing', function (): void {
    $hidden = ['eng.pride' => indicesResult('eng.pride', 80), 'eng.motivation' => indicesResult('eng.motivation', null), 'eng.connection' => indicesResult('eng.connection', 61)];
    $missing = ['eng.pride' => indicesResult('eng.pride', 80), 'eng.connection' => indicesResult('eng.connection', 61)];

    expect(Indices::engagement($hidden))->toBeNull()->and(Indices::engagement($missing))->toBeNull();
});

it('gives the recommendation index with 30 answers: promoters minus detractors', function (): void {
    $result = Indices::recommendation(indicesRecommendRows([10 => 10, 9 => 5, 8 => 5, 7 => 5, 6 => 3, 2 => 2]), resolve(Thresholds::class));

    expect($result->shown)->toBeTrue()->and($result->index)->toBe(33)
        ->and($result->bands)->toBe(['detractors' => 17, 'passives' => 33, 'promoters' => 50]);
});

it('gives the bands but no index with 29 answers', function (): void {
    $result = Indices::recommendation(indicesRecommendRows([9 => 15, 8 => 9, 3 => 5]), resolve(Thresholds::class));

    expect($result->shown)->toBeTrue()->and($result->index)->toBeNull()
        ->and($result->bands)->toBe(['detractors' => 17, 'passives' => 31, 'promoters' => 52]);
});

it('shows nothing with 4 answers and the bands with 5', function (): void {
    $four = Indices::recommendation(indicesRecommendRows([10 => 4], omitted: 20), resolve(Thresholds::class));
    $five = Indices::recommendation(indicesRecommendRows([10 => 5]), resolve(Thresholds::class));

    expect($four->shown)->toBeFalse()->and($four->index)->toBeNull()->and($four->bands)->toBeNull()
        ->and($five->shown)->toBeTrue()->and($five->bands)->toBe(['detractors' => 0, 'passives' => 0, 'promoters' => 100]);
});

it('puts the edges of the bands where the question says: 0-6, 7-8 and 9-10', function (): void {
    $result = Indices::recommendation(indicesRecommendRows([0 => 1, 6 => 1, 7 => 1, 8 => 1, 9 => 1, 10 => 1]), resolve(Thresholds::class));

    expect($result->bands)->toBe(['detractors' => 33, 'passives' => 33, 'promoters' => 33]);
});

it('is negative when there are more detractors than promoters, and the index uses exact counts, not the rounded bands', function (): void {
    $result = Indices::recommendation(indicesRecommendRows([10 => 8, 3 => 22]), resolve(Thresholds::class));

    expect($result->index)->toBe(-47);
});

it('leaves out of the count who omitted the question and the rows that never had it', function (): void {
    $rows = [...indicesRecommendRows([10 => 30], omitted: 5), new ResponseRow(new Segment(null, null, null, null), ['eng.pride' => 4])];

    $result = Indices::recommendation($rows, resolve(Thresholds::class));

    expect($result->index)->toBe(100);
});
