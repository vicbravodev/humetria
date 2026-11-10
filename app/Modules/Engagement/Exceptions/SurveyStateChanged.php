<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Exceptions;

use RuntimeException;

/**
 * Another request changed the survey between reading it and locking it (a double click, two tabs): what was asked no
 * longer applies, so nothing is done. Answered as 409 Conflict.
 */
final class SurveyStateChanged extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The survey changed meanwhile.');
    }

    public function render(): never
    {
        abort(409);
    }
}
