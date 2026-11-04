<?php

declare(strict_types=1);

use App\Modules\Results\Contracts\SmallGroups;
use App\Modules\Results\Support\GroupMinimum;

mutates(GroupMinimum::class);

it('is the platform minimum for other modules, taken from the one place it lives', function (): void {
    expect(resolve(SmallGroups::class))->toBeInstanceOf(GroupMinimum::class);
});

it('gives the keys of the groups that may be shown, the largest first', function (): void {
    config(['results.min_group' => 5]);

    expect(resolve(SmallGroups::class)->visibleKeys(['a' => 6, 'b' => 20, 'c' => 12]))->toBe(['b', 'c', 'a']);
});

it('hides a group under the minimum and takes the next smallest with it when what is hidden is still too little', function (): void {
    config(['results.min_group' => 5]);
    $groups = resolve(SmallGroups::class);

    expect($groups->visibleKeys(['a' => 20, 'b' => 4]))->toBe([])
        ->and($groups->visibleKeys(['a' => 20, 'b' => 12, 'c' => 3]))->toBe(['a'])
        ->and($groups->visibleKeys(['a' => 20, 'b' => 12, 'c' => 5]))->toBe(['a', 'b', 'c'])
        ->and($groups->visibleKeys(['a' => 20, 'b' => 12, 'c' => 2, 'd' => 3]))->toBe(['a', 'b']);
});

it('follows the minimum it is configured with', function (): void {
    config(['results.min_group' => 10]);

    // 9 is under 10, and hiding only it would give it away, so the smallest shown (15) goes with it.
    expect(resolve(SmallGroups::class)->visibleKeys(['a' => 20, 'b' => 9, 'c' => 15]))->toBe(['a'])
        ->and(resolve(SmallGroups::class)->visibleKeys(['a' => 20, 'b' => 10, 'c' => 15]))->toBe(['a', 'c', 'b']);
});

it('shows nothing for no groups', function (): void {
    expect(resolve(SmallGroups::class)->visibleKeys([]))->toBe([]);
});
