<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Audience;

use App\Modules\Engagement\Enums\CutAttribute;

/**
 * The coarse group a person was invited in: work center, area, tenure range and manager group. A null attribute is a
 * generalized one, so nobody can be told apart by it.
 */
final readonly class Segment
{
    public function __construct(
        public ?string $workCenterId,
        public ?string $areaId,
        public ?string $tenure,
        public ?string $managerGroupId,
    ) {}

    /** Equal for segments that share every attribute. */
    public function key(): string
    {
        return implode('|', [$this->workCenterId ?? '', $this->areaId ?? '', $this->tenure ?? '', $this->managerGroupId ?? '']);
    }

    public function value(CutAttribute $of): ?string
    {
        return match ($of) {
            CutAttribute::WorkCenter => $this->workCenterId,
            CutAttribute::Area => $this->areaId,
            CutAttribute::Tenure => $this->tenure,
            CutAttribute::Manager => $this->managerGroupId,
        };
    }

    public function without(CutAttribute $field): self
    {
        return new self(
            $field === CutAttribute::WorkCenter ? null : $this->workCenterId,
            $field === CutAttribute::Area ? null : $this->areaId,
            $field === CutAttribute::Tenure ? null : $this->tenure,
            $field === CutAttribute::Manager ? null : $this->managerGroupId,
        );
    }
}
