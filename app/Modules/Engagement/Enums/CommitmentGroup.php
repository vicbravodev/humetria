<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Enums;

/** Whom a commitment is for: the whole company or one group of a cut of the results. */
enum CommitmentGroup: string
{
    case Company = 'company';
    case WorkCenter = 'work_center';
    case Area = 'area';
    case Manager = 'manager';

    /** The cut of the results this group is shown in; the company has none. */
    public function cut(): ?CutAttribute
    {
        return match ($this) {
            self::Company => null,
            self::WorkCenter => CutAttribute::WorkCenter,
            self::Area => CutAttribute::Area,
            self::Manager => CutAttribute::Manager,
        };
    }
}
