<?php

declare(strict_types=1);

namespace App\Modules\Demos\Support\Tours;

use App\Modules\Demos\Data\StepState;

/** The «the audience answers» step of an evaluation or a survey: how many answered of how many were invited. */
final class Answers
{
    /**
     * Done when everyone invited answered, or when it closed: closing (even with nobody answering) must not leave the guide
     * stuck on a step that can no longer happen. With no invitation yet there is nothing to tell.
     */
    public static function state(bool $launched, int $invited, int $answered, bool $closed): StepState
    {
        if (! $launched) {
            return StepState::pending();
        }

        $live = $answered === 1 ? "Llegó 1 de {$invited} respuestas" : "Llegaron {$answered} de {$invited} respuestas";

        return new StepState($closed || ($answered >= 1 && $answered >= $invited), $live);
    }
}
