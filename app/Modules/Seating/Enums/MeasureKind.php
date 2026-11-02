<?php

declare(strict_types=1);

namespace App\Modules\Seating\Enums;

/**
 * The preventive measures of Quinta G of the Disposiciones, plus «other» (criterio C-10).
 */
enum MeasureKind: string
{
    case WorkstationDesign = 'workstation_design';
    case TaskRotation = 'task_rotation';
    case ErgonomicFootwear = 'ergonomic_footwear';
    case CushionedFloor = 'cushioned_floor';
    case ActiveBreaks = 'active_breaks';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::WorkstationDesign => 'Diseñar o adecuar la estación de trabajo',
            self::TaskRotation => 'Alternar tareas para cambiar de postura',
            self::ErgonomicFootwear => 'Proporcionar calzado ergonómico',
            self::CushionedFloor => 'Acondicionar el piso con superficie amortiguada',
            self::ActiveBreaks => 'Programa de pausas activas',
            self::Other => 'Otra medida',
        };
    }
}
