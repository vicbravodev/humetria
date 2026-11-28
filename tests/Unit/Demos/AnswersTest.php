<?php

declare(strict_types=1);

use App\Modules\Demos\Data\StepState;
use App\Modules\Demos\Support\Tours\Answers;

/*
 * The «the audience answers» step tells how many answered of how many were invited, in good Spanish: the noun follows the
 * number invited, the verb the number that answered. With nobody invited there is nothing to tell.
 */

mutates(Answers::class);

it('tells the answers live, in the singular when one was invited', function (int $invited, int $answered, string $live): void {
    expect(Answers::state(true, $invited, $answered, false)->live)->toBe($live);
})->with([
    'none of one' => [1, 0, 'Llegaron 0 de 1 respuesta'],
    'one of one' => [1, 1, 'Llegó 1 de 1 respuesta'],
    'one of five' => [5, 1, 'Llegó 1 de 5 respuestas'],
    'three of five' => [5, 3, 'Llegaron 3 de 5 respuestas'],
]);

it('tells nothing live while nobody was invited', function (): void {
    expect(Answers::state(true, 0, 0, false))->toEqual(new StepState(false, null))
        ->and(Answers::state(true, 0, 0, true))->toEqual(new StepState(true, null));
});

it('gives the guide the count apart from the sentence: how many answered, of how many, and the noun for that count', function (int $invited, int $answered, string $noun): void {
    $state = Answers::state(true, $invited, $answered, false);

    expect([$state->count, $state->of, $state->noun])->toBe([$answered, $invited, $noun]);
})->with([
    'none of five' => [5, 0, 'respuestas'],
    'one of five' => [5, 1, 'respuesta'],
    'three of five' => [5, 3, 'respuestas'],
]);
