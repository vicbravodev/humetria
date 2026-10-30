<?php

declare(strict_types=1);

namespace App\Modules\Responses\Contracts;

use App\Modules\Responses\Data\AnonymousAnswers;
use App\Modules\Responses\Data\TraumaAnswers;

/**
 * Stores answers in the current company. What was answered never reaches a log, and an anonymous answer keeps nothing
 * that leads back to its person (criterio C-22).
 */
interface ResponseRecorder
{
    public function recordAnonymous(AnonymousAnswers $answers): void;

    public function recordTraumaScreening(TraumaAnswers $answers): void;
}
