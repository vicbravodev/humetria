<?php

declare(strict_types=1);

use App\Support\SmallCells;

mutates(SmallCells::class);

it('shows both channels when every cell has at least the minimum', function (): void {
    expect(SmallCells::answeredByChannel(['whatsapp' => ['answered' => 5, 'unanswered' => 5], 'mail' => ['answered' => 6, 'unanswered' => 50]], 5))->toBe([
        ['channel' => 'mail', 'answered' => 6, 'unanswered' => 50],
        ['channel' => 'whatsapp', 'answered' => 5, 'unanswered' => 5],
    ]);
});

it('hides the whole table when any cell of a channel is below the minimum: 4 hides, 5 and 6 do not', function (int $cell, bool $shown): void {
    foreach (['answered', 'unanswered'] as $which) {
        $cells = ['mail' => ['answered' => 50, 'unanswered' => 50], 'whatsapp' => ['answered' => 50, 'unanswered' => 50]];
        $cells['whatsapp'][$which] = $cell;

        expect(SmallCells::answeredByChannel($cells, 5) !== null)->toBe($shown, "{$which} = {$cell}");
    }
})->with([[4, false], [5, true], [6, true], [0, false]]);

it('hides both channels when a single cell of either is small, since the other cells and the page total would give it away', function (): void {
    expect(SmallCells::answeredByChannel(['mail' => ['answered' => 3, 'unanswered' => 80], 'whatsapp' => ['answered' => 90, 'unanswered' => 90]], 5))->toBeNull()
        ->and(SmallCells::answeredByChannel(['mail' => ['answered' => 80, 'unanswered' => 80], 'whatsapp' => ['answered' => 90, 'unanswered' => 4]], 5))->toBeNull();
});

it('answers an empty list when there are no channels', function (): void {
    expect(SmallCells::answeredByChannel([], 5))->toBe([]);
});

it('uses the minimum it is given', function (): void {
    expect(SmallCells::answeredByChannel(['mail' => ['answered' => 9, 'unanswered' => 9]], 10))->toBeNull()
        ->and(SmallCells::answeredByChannel(['mail' => ['answered' => 10, 'unanswered' => 10]], 10))->not->toBeNull();
});
