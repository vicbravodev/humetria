<?php

declare(strict_types=1);

use App\Modules\Engagement\Support\Thresholds;

mutates(Thresholds::class);

it('meets the platform minimum at exactly 5 people and not at 4', function (): void {
    $thresholds = resolve(Thresholds::class);

    expect($thresholds->met(4))->toBeFalse()
        ->and($thresholds->met(5))->toBeTrue()
        ->and($thresholds->met(0))->toBeFalse();
});

it('follows results.min_group: Engagement writes no minimum of its own', function (): void {
    config(['results.min_group' => 7]);
    $thresholds = resolve(Thresholds::class);

    expect($thresholds->met(6))->toBeFalse()
        ->and($thresholds->met(7))->toBeTrue();
});

it('shows the comments of a group of 10 and not of 9', function (): void {
    $thresholds = resolve(Thresholds::class);

    expect($thresholds->comments(9))->toBeFalse()
        ->and($thresholds->comments(10))->toBeTrue();
});

it('needs the platform minimum for comments even when the comment minimum is lower', function (): void {
    config(['engagement.min_comments' => 3, 'results.min_group' => 7]);
    $thresholds = resolve(Thresholds::class);

    expect($thresholds->comments(6))->toBeFalse()
        ->and($thresholds->comments(7))->toBeTrue();
});

it('shows a trend or a recommendation index from 30 responses and not from 29', function (): void {
    $thresholds = resolve(Thresholds::class);

    expect($thresholds->trend(29))->toBeFalse()
        ->and($thresholds->trend(30))->toBeTrue();
});

it('lets Results decide which groups are visible, hiding a second one when one alone would give the first away', function (): void {
    expect(resolve(Thresholds::class)->visible(['a' => 10, 'b' => 6, 'c' => 3]))->toBe(['a'])
        ->and(resolve(Thresholds::class)->visible(['a' => 10, 'b' => 6, 'c' => 5]))->toBe(['a', 'b', 'c'])
        ->and(resolve(Thresholds::class)->visible([]))->toBe([]);
});
