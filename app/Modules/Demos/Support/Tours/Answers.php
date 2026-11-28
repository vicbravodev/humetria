<?php

declare(strict_types=1);

namespace App\Modules\Demos\Support\Tours;

use App\Modules\Demos\Data\StepState;

/** The «the audience answers» step of an evaluation or a survey: how many answered of how many were invited. */
final class Answers
{
    /**
     * Done when everyone invited answered, or when it closed: closing (even with nobody answering) must not leave the guide
     * stuck on a step that can no longer happen. With nobody invited there is nothing to tell live.
     */
    public static function state(bool $launched, int $invited, int $answered, bool $closed): StepState
    {
        if (! $launched) {
            return StepState::pending();
        }

        $done = $closed || ($answered >= 1 && $answered >= $invited);

        if ($invited === 0) {
            return new StepState($done);
        }

        $verb = $answered === 1 ? 'Llegó' : 'Llegaron';
        $noun = $invited === 1 ? 'respuesta' : 'respuestas';

        return new StepState($done, "{$verb} {$answered} de {$invited} {$noun}", $answered, $invited, $answered === 1 ? 'respuesta' : 'respuestas');
    }
}
