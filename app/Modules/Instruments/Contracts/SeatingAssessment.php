<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Contracts;

use App\Modules\Instruments\Data\AssessmentDefinition;
use App\Modules\Instruments\Data\AssessmentScore;
use App\Modules\Instruments\Data\SeatPlacement;
use InvalidArgumentException;

/**
 * The Ley Silla instrument: the seven conditions of the Disposiciones (Quinta D) with their points and risk levels, and the
 * decision of Figura 1 that says where the seat goes.
 */
interface SeatingAssessment
{
    public function definition(): AssessmentDefinition;

    /**
     * @param  array<string, int>  $points  The seven conditions, each from 1 to 3 points.
     *
     * @throws InvalidArgumentException With a message for whoever assesses when a condition is missing, unknown or out of range.
     */
    public function score(array $points): AssessmentScore;

    /**
     * Figura 1, questions 3 to 5: can the person work seated, is there room for a seat at the workstation, and must the
     * person be near it.
     */
    public function placement(bool $canWorkSeated, bool $hasSpaceForSeat, bool $needsToBeNearby): SeatPlacement;
}
