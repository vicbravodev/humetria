<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Data;

/** A place found by its address: how the provider names it and where it is. */
final readonly class Place
{
    public function __construct(
        public string $name,
        public float $latitude,
        public float $longitude,
    ) {}
}
