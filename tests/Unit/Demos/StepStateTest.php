<?php

declare(strict_types=1);

use App\Modules\Demos\Data\StepState;

/* A counted step gives the guide its count and the noun for it, apart from the sentence it tells live. */

it('gives the count and its noun, in the singular for one', function (): void {
    $one = StepState::counted(1, '1 marca en el centro', 'marcas en el centro');
    $four = StepState::counted(4, '1 marca en el centro', 'marcas en el centro');

    expect([$one->count, $one->of, $one->noun])->toBe([1, null, 'marca en el centro'])
        ->and([$four->count, $four->of, $four->noun])->toBe([4, null, 'marcas en el centro'])
        ->and(StepState::counted(0, '1 marca en el centro', 'marcas en el centro'))->toEqual(StepState::pending());
});
