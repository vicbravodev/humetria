<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Exceptions;

use RuntimeException;

/**
 * The evaluation has no Programa de intervención to speak of: it does not exist (or is another company's), it still runs,
 * or it applies only the Guía I. Whoever answers a request turns it into a 404.
 */
final class ProgramUnavailable extends RuntimeException
{
    public function render(): never
    {
        abort(404);
    }
}
