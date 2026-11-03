<?php

declare(strict_types=1);

namespace App\Modules\Seating\Data;

use App\Modules\Seating\Enums\MeasureKind;
use App\Modules\Seating\Enums\MeasureStatus;

/**
 * What RH fills in for a measure. `id` is null for a new one; the kind, the assessment, the position and the note are set
 * when it is created and never change.
 */
final readonly class MeasureData
{
    public function __construct(
        public ?string $id,
        public string $workCenterId,
        public ?MeasureKind $kind,
        public ?string $note,
        public ?string $assessmentId,
        public ?string $positionId,
        public MeasureStatus $status,
        public ?int $ownerUserId,
        public ?string $dueOn,
    ) {}
}
