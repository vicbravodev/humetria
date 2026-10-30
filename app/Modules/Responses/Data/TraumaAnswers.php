<?php

declare(strict_types=1);

namespace App\Modules\Responses\Data;

/**
 * A person's Guía I, stored under their name: whoever needs a clinical assessment must be channeled to care (num. 5.5).
 */
final readonly class TraumaAnswers
{
    /**
     * @param  array<array-key, mixed>  $answers  validated against Guía I
     */
    public function __construct(
        public string $campaignId,
        public string $employeeId,
        public string $version,
        public array $answers,
        public bool $requiresClinicalAssessment,
    ) {}
}
