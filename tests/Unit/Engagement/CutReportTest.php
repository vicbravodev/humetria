<?php

declare(strict_types=1);

use App\Modules\Engagement\Enums\CutAttribute;
use App\Modules\Engagement\Support\Audience\Segment;
use App\Modules\Engagement\Support\Results\CutGroups;
use App\Modules\Engagement\Support\Results\CutReport;
use App\Modules\Engagement\Support\Results\ResponseRow;
use App\Modules\Engagement\Support\Thresholds;
use Tests\TestCase;

uses(TestCase::class);

mutates(CutReport::class);

/**
 * `$count` responses of the same segment, numbered from `$from` so every row can be told apart in the assertions.
 *
 * @return list<ResponseRow>
 */
function cutRows(int $count, Segment $segment, int $from = 0): array
{
    $rows = [];
    for ($i = 0; $i < $count; $i++) {
        $rows[] = new ResponseRow($segment, ['n' => $from + $i]);
    }

    return $rows;
}

/**
 * @param  list<ResponseRow>  $rows
 * @return list<int>
 */
function cutIds(array $rows): array
{
    return array_map(fn (ResponseRow $row): int => (int) $row->answers['n'], $rows);
}

it('cuts by the attribute and keeps the groups of at least five', function (): void {
    $rows = [...cutRows(7, new Segment('a', null, null, null)), ...cutRows(6, new Segment('b', null, null, null), 7)];

    $groups = CutReport::of(CutAttribute::WorkCenter, $rows, resolve(Thresholds::class));

    expect(array_keys($groups->visible))->toBe(['a', 'b'])
        ->and(count($groups->visible['a']))->toBe(7)
        ->and(count($groups->visible['b']))->toBe(6)
        ->and($groups->hasHidden)->toBeFalse();
});

it('counts the responses of a small cell only at company level, so a center shows its own people and not the ones of a tiny area', function (): void {
    // Center X: 12 in area A and 2 and 2 in areas B and C. Those answers are company-only, so X shows 12, not 16 (the
    // smallest group shown, Z, goes too: the four left over would otherwise be the total minus the groups).
    $rows = [
        ...cutRows(12, new Segment('x', 'a', null, null)),
        ...cutRows(2, new Segment('x', 'b', null, null), 12),
        ...cutRows(2, new Segment('x', 'c', null, null), 14),
        ...cutRows(8, new Segment('y', 'd', null, null), 16),
        ...cutRows(7, new Segment('z', 'e', null, null), 24),
    ];

    $byCenter = CutReport::of(CutAttribute::WorkCenter, $rows, resolve(Thresholds::class));

    expect(array_map('count', $byCenter->visible))->toBe(['x' => 12, 'y' => 8])
        ->and($byCenter->hasHidden)->toBeTrue();
});

it('hides a group of five invited when only four answered and says something is hidden', function (): void {
    // The two groups of four are company-level: together they are eight, and the center of ten is shown.
    $rows = [...cutRows(10, new Segment('a', null, null, null)), ...cutRows(4, new Segment('b', null, null, null), 10), ...cutRows(4, new Segment('c', null, null, null), 14)];

    $groups = CutReport::of(CutAttribute::WorkCenter, $rows, resolve(Thresholds::class));

    expect(array_keys($groups->visible))->toBe(['a'])
        ->and($groups->hasHidden)->toBeTrue();
});

it('shows only the biggest of three groups of 10, 6 and 3, because the 3 would otherwise be the total minus the others', function (): void {
    $rows = [...cutRows(10, new Segment('a', null, null, null)), ...cutRows(6, new Segment('b', null, null, null), 10), ...cutRows(3, new Segment('c', null, null, null), 16)];

    $groups = CutReport::of(CutAttribute::WorkCenter, $rows, resolve(Thresholds::class));

    expect(array_keys($groups->visible))->toBe(['a'])
        ->and($groups->hasHidden)->toBeTrue();
});

it('does not say something is hidden when every response belongs to a shown group', function (): void {
    $groups = CutReport::of(CutAttribute::Area, cutRows(5, new Segment(null, 'a', null, null)), resolve(Thresholds::class));

    expect($groups->hasHidden)->toBeFalse();
});

it('says something is hidden when some responses belong to no group of that attribute', function (): void {
    $rows = [...cutRows(6, new Segment(null, 'a', null, null)), ...cutRows(6, new Segment(null, null, null, null), 6)];

    $groups = CutReport::of(CutAttribute::Area, $rows, resolve(Thresholds::class));

    expect(array_keys($groups->visible))->toBe(['a'])
        ->and($groups->hasHidden)->toBeTrue();
});

it('cuts by manager group with the groups the managers will see', function (): void {
    $rows = [...cutRows(5, new Segment(null, null, null, 'boss-1')), ...cutRows(9, new Segment(null, null, null, 'boss-2'), 5)];

    $groups = CutReport::of(CutAttribute::Manager, $rows, resolve(Thresholds::class));

    expect(array_map('count', $groups->visible))->toBe(['boss-2' => 9, 'boss-1' => 5]);
});

it('returns nothing for no responses', function (): void {
    $groups = CutReport::of(CutAttribute::Tenure, [], resolve(Thresholds::class));

    expect($groups->visible)->toBe([])->and($groups->hasHidden)->toBeFalse();
});

it('has no number about what is hidden', function (): void {
    $properties = (new ReflectionClass(CutGroups::class))->getProperties();

    expect(array_map(fn (ReflectionProperty $property): string => $property->getName().':'.$property->getType(), $properties))
        ->toBe(['visible:array', 'hasHidden:bool']);
});

/**
 * 200 synthetic companies, every attribute drawn at random (some people with no manager or no start date). For every
 * pair of groups shown in any of the four cuts, and each against the whole company, if one contains the other the
 * people between them are none or at least five: nobody can be found by subtracting two numbers they were shown.
 */
it('never lets two shown groups, or one and the company, differ by one to four people', function (): void {
    mt_srand(20261109);
    $thresholds = resolve(Thresholds::class);

    for ($company = 0; $company < 200; $company++) {
        $size = mt_rand(5, 70);
        $rows = [];
        for ($i = 0; $i < $size; $i++) {
            $rows[] = new ResponseRow(new Segment(
                'wc'.mt_rand(1, 3),
                'ar'.mt_rand(1, 4),
                mt_rand(0, 9) === 0 ? null : 'te'.mt_rand(1, 4),
                mt_rand(0, 9) === 0 ? null : 'mg'.mt_rand(1, 5),
            ), ['n' => $i]);
        }

        $shown = [['all', range(0, $size - 1)]];
        foreach (CutAttribute::cases() as $by) {
            foreach (CutReport::of($by, $rows, $thresholds)->visible as $key => $members) {
                $shown[] = ["{$by->value}:{$key}", cutIds($members)];
            }
        }

        foreach ($shown as [$nameA, $idsA]) {
            foreach ($shown as [$nameB, $idsB]) {
                if ($nameA === $nameB || array_diff($idsB, $idsA) !== []) {
                    continue;
                }
                $difference = count($idsA) - count($idsB);
                expect($difference < 1 || $difference >= 5)->toBeTrue("company {$company}: {$nameA} minus {$nameB} is {$difference}");
            }
        }
    }
});
