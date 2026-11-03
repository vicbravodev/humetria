<?php

declare(strict_types=1);

namespace App\Modules\Seating\Data;

use App\Modules\Seating\Enums\SeatCondition;
use App\Modules\Seating\Enums\SeatKind;
use App\Modules\Seating\Enums\SeatUse;

/**
 * What RH fills in for a seat or a group of equal ones. `id` is null for a new one; `characteristics` holds the nine
 * characteristics of Quinta F by name.
 */
final readonly class SeatData
{
    /**
     * @param  array<string, bool>  $characteristics
     */
    public function __construct(
        public ?string $id,
        public string $workCenterId,
        public ?string $positionId,
        public ?string $location,
        public SeatKind $kind,
        public SeatUse $use,
        public int $quantity,
        public array $characteristics,
        public SeatCondition $condition,
        public bool $signposted,
    ) {}
}
