<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Data;

/**
 * What a Guía I says about a person: whether they went through a severe traumatic event at work, and whether they need
 * a clinical assessment and must be channeled to care (num. 5.5).
 */
final readonly class TraumaOutcome
{
    public function __construct(public bool $experiencedEvent, public bool $requiresClinicalAssessment) {}
}
