<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Data;

/**
 * Where a position falls against the zones of a work center: the nearest zone and the distance to its center, whether
 * the position is inside it, and whether the device's accuracy is good enough to trust it (criterio 3.4).
 */
final readonly class ZoneReading
{
    public function __construct(
        public ?string $zoneId,
        public ?float $distanceM,
        public bool $inside,
        public bool $accuracyOk,
    ) {}
}
