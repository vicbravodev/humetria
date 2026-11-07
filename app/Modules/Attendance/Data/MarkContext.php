<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Data;

use App\Modules\Attendance\Enums\MarkMethod;

/** How and where a mark was made, as the checks of the device left it (criterios 3.4 and 5.3). */
final readonly class MarkContext
{
    public function __construct(
        public MarkMethod $method,
        public ?string $zoneId,
        public ?float $latitude,
        public ?float $longitude,
        public ?float $accuracy,
        public ?float $distance,
        public ?string $deviceId,
    ) {}
}
