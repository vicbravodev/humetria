<?php

declare(strict_types=1);

namespace App\Modules\Results\Data;

/**
 * What HR fills in for an evaluation's report (num. 7.7).
 */
final readonly class ReportDetailsData
{
    public function __construct(
        public string $objective,
        public string $activities,
        public string $conclusions,
        public string $recommendations,
        public string $responsibleName,
        public ?string $responsibleLicense,
    ) {}
}
