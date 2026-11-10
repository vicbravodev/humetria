<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Audience;

/**
 * The coarse place of a person in the company, as the invitation snapshot keeps it; `null` means "the whole company".
 *
 * ponytail: only the constructor, which is what ResponseRow needs. Task 7 adds key(), value() and without().
 */
final readonly class Segment
{
    public function __construct(
        public ?string $workCenterId,
        public ?string $areaId,
        public ?string $tenure,
        public ?string $managerGroupId,
    ) {}
}
