<?php

declare(strict_types=1);

use App\Modules\Results\Support\GroupMinimum;
use Tests\TestCase;

uses(TestCase::class);

mutates(GroupMinimum::class);

beforeEach(fn () => config(['results.min_group' => 5]));

it('hides a group of four and shows one of five', function (int $answered, bool $shown): void {
    expect(resolve(GroupMinimum::class)->isShown($answered))->toBe($shown);
})->with([[0, false], [4, false], [5, true], [6, true]]);

it('follows the configured minimum', function (): void {
    config(['results.min_group' => 8]);

    expect(resolve(GroupMinimum::class)->isShown(7))->toBeFalse()
        ->and(resolve(GroupMinimum::class)->isShown(8))->toBeTrue();
});

it('hides the groups under the minimum and lists the others largest first, by id on a tie', function (): void {
    $result = resolve(GroupMinimum::class)->suppress(['b' => 6, 'a' => 6, 'c' => 9, 'd' => 5]);

    expect($result->visible)->toBe(['c', 'a', 'b', 'd'])->and($result->hidden)->toBe(0);
});

it('counts the groups it hides', function (): void {
    $result = resolve(GroupMinimum::class)->suppress(['a' => 7, 'b' => 3, 'c' => 5, 'd' => 2]);

    expect($result->visible)->toBe(['a', 'c'])->and($result->hidden)->toBe(2);
});

it('hides the smallest group shown while what is hidden adds up to fewer than the minimum', function (): void {
    // b (2) is hidden; the total minus a and c would be 2 and reveal b, so c (5) goes too: 2 + 5 = 7.
    $result = resolve(GroupMinimum::class)->suppress(['a' => 8, 'b' => 2, 'c' => 5]);

    expect($result->visible)->toBe(['a'])->and($result->hidden)->toBe(2);
});

it('keeps hiding the smallest shown, ties by id, until the hidden ones are none or enough', function (): void {
    // x, y hidden (1 + 1 = 2); then the smallest shown (a: 5, by id before b) joins them: 7 is enough.
    $result = resolve(GroupMinimum::class)->suppress(['x' => 1, 'y' => 1, 'a' => 5, 'b' => 5]);

    expect($result->visible)->toBe(['b'])->and($result->hidden)->toBe(3);
});

it('leaves everything shown when nothing is hidden and nothing when nothing reaches the minimum', function (): void {
    expect(resolve(GroupMinimum::class)->suppress(['a' => 5, 'b' => 9])->visible)->toBe(['b', 'a'])
        ->and(resolve(GroupMinimum::class)->suppress(['a' => 2, 'b' => 3])->visible)->toBe([])
        ->and(resolve(GroupMinimum::class)->suppress([])->hidden)->toBe(0);
});
