<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Data;

/** Where the device said it was when the person marked, and how accurate it said it was, in meters (criterio 3.4). */
final readonly class Position
{
    public function __construct(
        public float $latitude,
        public float $longitude,
        public float $accuracyM,
    ) {}
}
