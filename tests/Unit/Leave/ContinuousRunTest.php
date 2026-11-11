<?php

declare(strict_types=1);

use App\Modules\Leave\Support\ContinuousRun;

mutates(ContinuousRun::class);

// Criterio C-06 (LFT art. 78): twelve continuous days at least; the person is told, never stopped.
it('says when no request of the period reaches twelve working days in a row', function (array $requests, bool $missing): void {
    expect(ContinuousRun::missing($requests))->toBe($missing);
})->with([
    'two short ones that add up to twelve' => [[5, 7], true],
    'one of twelve' => [[12], false],
    'one of eleven' => [[11], true],
    'none' => [[], true],
    'a long one among short ones' => [[3, 15, 2], false],
    'the long one last' => [[3, 12], false],
]);

it('takes the twelve days from the law', function (): void {
    expect(ContinuousRun::MINIMUM)->toBe(12);
});
