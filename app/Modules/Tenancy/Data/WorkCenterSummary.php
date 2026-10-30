<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Data;

final readonly class WorkCenterSummary
{
    public function __construct(
        public string $id,
        public string $name,
        public bool $archived,
        /** What is done there (actividad principal), as a card describes it. */
        public string $mainActivity,
    ) {}
}
