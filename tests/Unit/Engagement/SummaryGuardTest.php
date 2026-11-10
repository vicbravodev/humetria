<?php

declare(strict_types=1);

use App\Modules\Engagement\Exceptions\UnsafeSummary;
use App\Modules\Engagement\Support\Comments\SummaryGuard;

mutates(SummaryGuard::class);

/**
 * @return list<string>
 */
function guardComments(int $count = 12): array
{
    return array_merge(['Nos falta personal en la bodega los viernes por la tarde'], array_fill(0, $count - 1, 'Todo bien'));
}

it('keeps the themes with enough support and drops the ones below it', function (): void {
    $clean = SummaryGuard::clean(
        ['valued' => [['theme' => 'Trato respetuoso', 'count' => 3], ['theme' => 'Buen horario', 'count' => 2]], 'change' => [['theme' => 'Más comunicación', 'count' => 5]]],
        guardComments(),
        3,
    );

    expect($clean)->toBe(['valued' => [['theme' => 'Trato respetuoso', 'count' => 3]], 'change' => [['theme' => 'Más comunicación', 'count' => 5]]]);
});

it('rejects a theme that repeats five words in a row of a comment, with no case, accents or punctuation', function (): void {
    $theme = fn (string $text): array => ['valued' => [['theme' => $text, 'count' => 4]], 'change' => []];

    expect(fn () => SummaryGuard::clean($theme('NOS FALTA personal, en la bodega'), guardComments(), 3))->toThrow(UnsafeSummary::class)
        ->and(fn () => SummaryGuard::clean($theme('Falta personal en la bodega'), guardComments(), 3))->toThrow(UnsafeSummary::class);
});

it('accepts a theme that repeats only four words in a row', function (): void {
    $clean = SummaryGuard::clean(['valued' => [['theme' => 'Falta personal en la planta', 'count' => 4]], 'change' => []], guardComments(), 3);

    expect($clean['valued'])->toHaveCount(1);
});

it('rejects digits, quotes, long themes and counts above the number of comments', function (array $theme): void {
    expect(fn () => SummaryGuard::clean(['valued' => [$theme], 'change' => []], guardComments(10), 3))->toThrow(UnsafeSummary::class);
})->with([
    'digits' => [['theme' => 'Salida el 3 de marzo', 'count' => 4]],
    'double quotes' => [['theme' => 'Dicen "gracias"', 'count' => 4]],
    'angle quotes' => [['theme' => 'Dicen «gracias»', 'count' => 4]],
    'single quote' => [['theme' => "Dicen 'gracias'", 'count' => 4]],
    'too long' => [['theme' => str_repeat('a', 161), 'count' => 4]],
    'more than the comments' => [['theme' => 'Trato respetuoso', 'count' => 11]],
]);

it('accepts a theme of exactly 160 characters and a count equal to the comments', function (): void {
    $clean = SummaryGuard::clean(['valued' => [['theme' => str_repeat('a', 160), 'count' => 10]], 'change' => []], guardComments(10), 3);

    expect($clean['valued'])->toHaveCount(1);
});

it('rejects an answer that is not the shape asked', function (array $themes): void {
    expect(fn () => SummaryGuard::clean($themes, guardComments(), 3))->toThrow(UnsafeSummary::class);
})->with([
    'no change list' => [['valued' => []]],
    'theme not text' => [['valued' => [['theme' => 5, 'count' => 4]], 'change' => []]],
    'count not a number' => [['valued' => [['theme' => 'Trato', 'count' => 'cuatro']], 'change' => []]],
    'item not a map' => [['valued' => ['Trato'], 'change' => []]],
]);
