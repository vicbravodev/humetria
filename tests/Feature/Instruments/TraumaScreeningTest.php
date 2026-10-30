<?php

declare(strict_types=1);

use App\Modules\Instruments\Contracts\Instruments;
use App\Modules\Instruments\Support\Nom035\GuideOne;
use App\Modules\Instruments\Support\TraumaScreener;

mutates(TraumaScreener::class, GuideOne::class);

/**
 * GR.I: no event in section I ends the questionnaire with no assessment; after one, the person needs a clinical
 * assessment with at least 1 "yes" in section II (items 7–8), or 3 in section III (9–15), or 2 in section IV (16–20).
 */
it('decides the clinical assessment exactly as GR.I says', function (array $yes, bool $event, bool $assessment): void {
    $outcome = resolve(Instruments::class)->screenTrauma(guideOneAnswers($yes));

    expect($outcome->experiencedEvent)->toBe($event)
        ->and($outcome->requiresClinicalAssessment)->toBe($assessment);
})->with([
    'no event' => [[], false, false],
    'an event and nothing else' => [[1], true, false],
    'the last event of section I and nothing else' => [[6], true, false],
    'section II, one' => [[2, 7], true, true],
    'section II, the other' => [[2, 8], true, true],
    'section III, two' => [[3, 9, 15], true, false],
    'section III, three' => [[3, 9, 12, 15], true, true],
    'section IV, one' => [[4, 20], true, false],
    'section IV, two' => [[4, 16, 20], true, true],
    'II none, III two, IV one' => [[5, 10, 11, 18], true, false],
    'every answer yes' => [range(1, 20), true, true],
]);

it('screens only a valid Guía I', function (): void {
    resolve(Instruments::class)->screenTrauma(array_replace(guideOneAnswers(), ['7' => 'yes']));
})->throws(InvalidArgumentException::class);
