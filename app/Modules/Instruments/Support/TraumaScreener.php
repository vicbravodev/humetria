<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Support;

use App\Modules\Instruments\Data\TraumaOutcome;
use App\Modules\Instruments\Support\Nom035\GuideOne;

/**
 * Guía I, GR.I: after an event in section I, a clinical assessment is required with at least one "yes" in section II,
 * three in section III or two in section IV.
 */
final class TraumaScreener
{
    /**
     * @param  array<array-key, mixed>  $answers  a valid Guía I
     */
    public function screen(array $answers): TraumaOutcome
    {
        if (! GuideOne::continuesAfterSectionOne($answers)) {
            return new TraumaOutcome(experiencedEvent: false, requiresClinicalAssessment: false);
        }

        $requires = $this->yesCount($answers, range(7, 8)) >= 1
            || $this->yesCount($answers, range(9, 15)) >= 3
            || $this->yesCount($answers, range(16, 20)) >= 2;

        return new TraumaOutcome(experiencedEvent: true, requiresClinicalAssessment: $requires);
    }

    /**
     * @param  array<array-key, mixed>  $answers
     * @param  list<int>  $items
     */
    private function yesCount(array $answers, array $items): int
    {
        return count(array_filter($items, fn (int $number): bool => ($answers[$number] ?? null) === 'yes'));
    }
}
