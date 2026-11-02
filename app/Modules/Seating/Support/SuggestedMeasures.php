<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use App\Modules\Instruments\Data\RiskLevel;
use App\Modules\Seating\Enums\MeasureKind;

/**
 * The measures of Quinta G the platform suggests for a level. The Disposiciones tie none of them to a level (criterio C-10):
 * this is Humetría's suggestion, labelled as such, and the Comisión decides.
 */
final class SuggestedMeasures
{
    public const string LABEL = 'Sugerencia de Humetría';

    /**
     * @return list<MeasureKind>
     */
    public static function for(RiskLevel $level): array
    {
        return match ($level) {
            RiskLevel::High => [MeasureKind::WorkstationDesign, MeasureKind::TaskRotation, MeasureKind::ErgonomicFootwear, MeasureKind::CushionedFloor, MeasureKind::ActiveBreaks],
            RiskLevel::Medium => [MeasureKind::TaskRotation, MeasureKind::ErgonomicFootwear, MeasureKind::ActiveBreaks],
            default => [MeasureKind::ActiveBreaks],
        };
    }
}
