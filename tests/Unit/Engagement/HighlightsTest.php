<?php

declare(strict_types=1);

use App\Modules\Engagement\Enums\Driver;
use App\Modules\Engagement\Support\Bank\ItemBank;
use App\Modules\Engagement\Support\Results\Highlights;
use App\Modules\Engagement\Support\Results\ItemResult;

mutates(Highlights::class);

/**
 * The first `$count` company questions, in the order of the bank, each with the favorable share `$favorable($position)`.
 *
 * @param  Closure(int): ?int  $favorable  null = hidden
 * @return list<ItemResult>
 */
function highlightsItems(int $count, Closure $favorable): array
{
    $items = [];
    foreach (Driver::cases() as $driver) {
        foreach (ItemBank::ofDriver($driver) as $item) {
            $value = $favorable(count($items));
            $items[] = $value === null
                ? new ItemResult($item->key, false, null, null, null, null, null)
                : new ItemResult($item->key, true, 40, $value, 0, 100 - $value, [0, 0, 0, 0, 0]);
        }
    }

    return array_slice($items, 0, $count);
}

/**
 * @param  list<ItemResult>  $items
 * @return list<string>
 */
function highlightsKeys(array $items): array
{
    return array_map(fn (ItemResult $item): string => $item->key, $items);
}

it('picks the 3 best and the 3 worst of the 27 company questions, none in both lists', function (): void {
    $items = highlightsItems(27, fn (int $position): int => 3 * ($position + 1));

    $picked = Highlights::pick($items, 3);

    expect(highlightsKeys($picked['strengths']))->toBe(highlightsKeys([$items[26], $items[25], $items[24]]))
        ->and(highlightsKeys($picked['opportunities']))->toBe(highlightsKeys([$items[0], $items[1], $items[2]]));
});

it('lowers the count to half of the shown questions so a question is never in both lists', function (): void {
    $items = highlightsItems(5, fn (int $position): int => 10 * ($position + 1));

    $picked = Highlights::pick($items, 3);

    expect($picked['strengths'])->toHaveCount(2)->and($picked['opportunities'])->toHaveCount(2)
        ->and(array_intersect(highlightsKeys($picked['strengths']), highlightsKeys($picked['opportunities'])))->toBe([]);
});

it('picks nothing from a single question and one of each from two', function (): void {
    expect(Highlights::pick(highlightsItems(1, fn (): int => 50), 3))->toBe(['strengths' => [], 'opportunities' => []]);

    $two = Highlights::pick(highlightsItems(2, fn (int $position): int => 10 + $position), 3);

    expect($two['strengths'])->toHaveCount(1)->and($two['opportunities'])->toHaveCount(1);
});

it('counts only the shown questions', function (): void {
    $items = highlightsItems(6, fn (int $position): ?int => $position < 3 ? null : 10 * $position);

    $picked = Highlights::pick($items, 3);

    expect(highlightsKeys($picked['strengths']))->toBe(highlightsKeys([$items[5]]))->and(highlightsKeys($picked['opportunities']))->toBe(highlightsKeys([$items[3]]));
});

it('breaks a tie by the order of the bank, in both lists', function (): void {
    $items = highlightsItems(4, fn (): int => 50);

    $picked = Highlights::pick($items, 2);

    expect(highlightsKeys($picked['strengths']))->toBe(highlightsKeys([$items[0], $items[1]]))->and(highlightsKeys($picked['opportunities']))->toBe(highlightsKeys([$items[2], $items[3]]));
});

it('does not depend on the order the questions come in', function (): void {
    $items = highlightsItems(27, fn (int $position): int => 3 * ($position + 1));

    expect(Highlights::pick(array_reverse($items), 3))->toEqual(Highlights::pick($items, 3));
});

it('picks the manager block with 2 of each out of the 8', function (): void {
    $items = array_map(
        fn ($item): ItemResult => new ItemResult($item->key, true, 40, 50, 0, 50, [0, 0, 0, 0, 0]),
        array_values(array_filter(ItemBank::all(), fn ($item): bool => $item->block === App\Modules\Engagement\Enums\ItemBlock::Manager && $item->scale === App\Modules\Engagement\Enums\ItemScale::Agreement)),
    );

    $picked = Highlights::pick($items, 2);

    expect($items)->toHaveCount(8)->and($picked['strengths'])->toHaveCount(2)->and($picked['opportunities'])->toHaveCount(2);
});
