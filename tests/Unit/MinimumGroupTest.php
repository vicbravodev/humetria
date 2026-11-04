<?php

declare(strict_types=1);

use App\Modules\Complaints\Support\MinimumGroup;

mutates(MinimumGroup::class);

it('shows the cells that reach the minimum and hides the ones that do not', function (array $counts, array $expected): void {
    expect(MinimumGroup::apply($counts, 5))->toBe($expected);
})->with([
    'every cell reaches it' => [['a' => 5, 'b' => 9, 'c' => 5], ['a' => 5, 'b' => 9, 'c' => 5]],
    'one below, hidden with enough hidden to hide it' => [['a' => 10, 'b' => 3, 'c' => 4], ['a' => 10, 'b' => null, 'c' => null]],
    'a hidden cell alone would be given away by the total, so the smallest shown goes too' => [['a' => 10, 'b' => 6, 'c' => 3], ['a' => 10, 'b' => null, 'c' => null]],
    'what is hidden already adds up to the minimum' => [['a' => 10, 'b' => 2, 'c' => 3], ['a' => 10, 'b' => null, 'c' => null]],
    'a cell just below the limit takes the smallest shown one with it' => [['a' => 9, 'b' => 12, 'c' => 4], ['a' => null, 'b' => 12, 'c' => null]],
    'an empty cell has nobody to protect' => [['a' => 8, 'b' => 7, 'c' => 0], ['a' => 8, 'b' => 7, 'c' => 0]],
    'everything below the minimum' => [['a' => 2, 'b' => 1, 'c' => 0], ['a' => null, 'b' => null, 'c' => 0]],
    'only as many shown cells go as it takes' => [['a' => 20, 'b' => 7, 'c' => 6, 'd' => 1], ['a' => 20, 'b' => 7, 'c' => null, 'd' => null]],
    'as many as it takes, one after the other' => [['a' => 20, 'b' => 6, 'c' => 5, 'd' => 1], ['a' => 20, 'b' => 6, 'c' => null, 'd' => null]],
]);

it('shows the total only when it reaches the minimum', function (int $total, ?int $shown): void {
    expect(MinimumGroup::total($total, 5))->toBe($shown);
})->with([[0, 0], [4, null], [5, 5], [6, 6]]);

it('takes the minimum of the limit exactly: 4 is hidden and 5 is shown', function (): void {
    expect(MinimumGroup::apply(['a' => 4, 'b' => 50], 5))->toBe(['a' => null, 'b' => null])
        ->and(MinimumGroup::apply(['a' => 5, 'b' => 50], 5))->toBe(['a' => 5, 'b' => 50]);
});
