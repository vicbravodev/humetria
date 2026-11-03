<?php

declare(strict_types=1);

namespace App\Modules\Seating\Data;

use App\Modules\Seating\Enums\BipedestationType;

/**
 * What RH fills in for a standing position. `id` is null for a new one. The result of Figura 1 is not here: it is derived.
 */
final readonly class PositionData
{
    /**
     * @param  array<string, string|null>  $areaConditions  noise, lighting, ventilation, temperature, vibration and chemicals.
     */
    public function __construct(
        public ?string $id,
        public string $workCenterId,
        public string $name,
        public string $tasks,
        public BipedestationType $type,
        public int $continuousMinutes,
        public bool $canWorkSeated,
        public bool $hasSpaceForSeat,
        public bool $needsToBeNearby,
        public int $maxPeopleAtOnce,
        public array $areaConditions,
        public ?string $tools,
        public ?string $physicalLoad,
        public ?string $attentionLevel,
        public ?int $defaultStandingTime,
        public ?int $defaultPostureChange,
        public ?int $defaultSurface,
        public ?int $defaultMovementSpace,
        public ?int $defaultPauses,
        public ?bool $natureAllows,
        public ?string $natureReason,
    ) {}
}
