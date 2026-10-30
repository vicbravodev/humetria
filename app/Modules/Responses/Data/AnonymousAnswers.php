<?php

declare(strict_types=1);

namespace App\Modules\Responses\Data;

/**
 * A Guía II or III to store anonymously: where it was answered (campaign, work center, area) and what, never by whom.
 */
final readonly class AnonymousAnswers
{
    /**
     * @param  array<array-key, mixed>  $answers  validated against the instrument
     */
    public function __construct(
        public string $campaignId,
        public string $workCenterId,
        public string $areaId,
        public string $instrument,
        public string $version,
        public array $answers,
    ) {}
}
