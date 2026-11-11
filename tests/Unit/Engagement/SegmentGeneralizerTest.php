<?php

declare(strict_types=1);

use App\Modules\Engagement\Enums\CutAttribute;
use App\Modules\Engagement\Support\Audience\Segment;
use App\Modules\Engagement\Support\Audience\SegmentGeneralizer;

mutates(SegmentGeneralizer::class, Segment::class);

/** @return Closure(int): bool */
function atLeastFive(): Closure
{
    return fn (int $n): bool => $n >= 5;
}

/**
 * @return array<string, Segment>
 */
function sameSegment(int $count, Segment $segment, string $prefix = 'r'): array
{
    $rows = [];
    for ($i = 1; $i <= $count; $i++) {
        $rows["{$prefix}{$i}"] = $segment;
    }

    return $rows;
}

it('exposes and removes the value of one attribute', function (): void {
    $segment = new Segment('wc', 'area', '1to3', 'boss');

    expect($segment->value(CutAttribute::WorkCenter))->toBe('wc')
        ->and($segment->value(CutAttribute::Area))->toBe('area')
        ->and($segment->value(CutAttribute::Tenure))->toBe('1to3')
        ->and($segment->value(CutAttribute::Manager))->toBe('boss')
        ->and($segment->without(CutAttribute::Area))->toEqual(new Segment('wc', null, '1to3', 'boss'))
        ->and($segment->without(CutAttribute::Manager)->key())->not->toBe($segment->key())
        ->and($segment->key())->toBe((new Segment('wc', 'area', '1to3', 'boss'))->key());
});

it('leaves a combination of five people as it is', function (): void {
    $rows = sameSegment(5, new Segment('wc', 'area', '1to3', 'boss'));

    expect(SegmentGeneralizer::generalize($rows, atLeastFive()))->toEqual($rows);
});

it('drops the tenure of four people first', function (): void {
    $rows = sameSegment(4, new Segment('wc', 'area', '1to3', 'boss')) + sameSegment(1, new Segment('wc', 'area', '3to6', 'boss'), 's') + sameSegment(5, new Segment('wc', 'area', '6plus', 'boss'), 't');

    $result = SegmentGeneralizer::generalize($rows, atLeastFive());

    expect($result['r1'])->toEqual(new Segment('wc', 'area', null, 'boss'))
        ->and($result['s1'])->toEqual(new Segment('wc', 'area', null, 'boss'))
        ->and($result['t1'])->toEqual(new Segment('wc', 'area', '6plus', 'boss'));
});

it('drops the tenure and then the area, the work center and the manager while they stay under five', function (): void {
    $rows = sameSegment(4, new Segment('wc', 'area', '1to3', 'boss'));

    expect(SegmentGeneralizer::generalize($rows, atLeastFive())['r1'])->toEqual(new Segment(null, null, null, null));
});

it('keeps the area once the people who dropped their tenure make five', function (): void {
    $rows = sameSegment(3, new Segment('wc', 'area', '1to3', 'boss'))
        + sameSegment(2, new Segment('wc', 'area', '3to6', 'boss'), 's');

    $result = SegmentGeneralizer::generalize($rows, atLeastFive());

    expect($result['r1'])->toEqual(new Segment('wc', 'area', null, 'boss'))
        ->and($result['s1'])->toEqual(new Segment('wc', 'area', null, 'boss'));
});

it('sends an area of three in a center of twelve to the company, when the center alone would not reach five', function (): void {
    $rows = sameSegment(9, new Segment('wc', 'big', '1to3', null), 'b') + sameSegment(3, new Segment('wc', 'small', '1to3', null), 's');

    $result = SegmentGeneralizer::generalize($rows, atLeastFive());

    expect($result['b1'])->toEqual(new Segment('wc', 'big', '1to3', null))
        ->and($result['s1'])->toEqual(new Segment(null, null, null, null));
});

it('keeps the center of an area of three when the center without area reaches five', function (): void {
    $rows = sameSegment(9, new Segment('wc', 'big', '1to3', null), 'b')
        + sameSegment(3, new Segment('wc', 'small', '1to3', null), 's')
        + sameSegment(2, new Segment('wc', null, '1to3', null), 'c');

    $result = SegmentGeneralizer::generalize($rows, atLeastFive());

    expect($result['s1'])->toEqual(new Segment('wc', null, null, null))
        ->and($result['c1'])->toEqual(new Segment('wc', null, null, null));
});

it('leaves no combination under five except the whole company, in any group of people', function (int $seed): void {
    mt_srand($seed);
    $rows = [];
    for ($i = 0; $i < 150; $i++) {
        $rows["p{$i}"] = new Segment(
            mt_rand(0, 2) === 0 ? null : 'wc'.mt_rand(1, 3),
            mt_rand(0, 3) === 0 ? null : 'a'.mt_rand(1, 6),
            mt_rand(0, 3) === 0 ? null : ['lt1', '1to3', '3to6', '6plus'][mt_rand(0, 3)],
            mt_rand(0, 3) === 0 ? null : 'boss'.mt_rand(1, 8),
        );
    }

    $result = SegmentGeneralizer::generalize($rows, atLeastFive());

    $sizes = array_count_values(array_map(fn (Segment $segment): string => $segment->key(), $result));
    $company = (new Segment(null, null, null, null))->key();
    foreach ($sizes as $key => $size) {
        expect($key === $company || $size >= 5)->toBeTrue("{$key} has {$size}");
    }
    expect(array_keys($result))->toBe(array_keys($rows));
})->with([1, 2, 3, 20261109]);

it('does not depend on the order of the rows', function (): void {
    mt_srand(99);
    $rows = [];
    for ($i = 0; $i < 60; $i++) {
        $rows["p{$i}"] = new Segment('wc'.mt_rand(1, 2), 'a'.mt_rand(1, 5), ['lt1', '1to3'][mt_rand(0, 1)], null);
    }
    $shuffled = $rows;
    $keys = array_keys($shuffled);
    mt_srand(5);
    shuffle($keys);
    $shuffled = array_replace(array_flip($keys), $rows);

    expect(SegmentGeneralizer::generalize($shuffled, atLeastFive()))->toEqualCanonicalizing(SegmentGeneralizer::generalize($rows, atLeastFive()));
});
