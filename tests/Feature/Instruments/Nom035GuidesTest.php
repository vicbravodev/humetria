<?php

declare(strict_types=1);

use App\Modules\Instruments\Contracts\Nom035Guides;
use App\Modules\Instruments\Support\Nom035GuideRule;

mutates(Nom035GuideRule::class);

/**
 * NOM-035 (num. 4.4 and 2): up to 15 workers, Guía I; 16 to 50, Guías I and II; more than 50, Guías I and III.
 * Guía I applies to everyone; with nobody, nothing applies yet.
 */
it('says which guides apply to a work center by its number of workers', function (int $workers, array $guides): void {
    expect(resolve(Nom035Guides::class)->for($workers))->toBe($guides);
})->with([
    'nobody' => [0, []],
    'one' => [1, ['I']],
    'fifteen' => [15, ['I']],
    'sixteen' => [16, ['I', 'II']],
    'fifty' => [50, ['I', 'II']],
    'fifty-one' => [51, ['I', 'III']],
    'a thousand' => [1000, ['I', 'III']],
]);
