<?php

declare(strict_types=1);

namespace App\Modules\Telework\Enums;

/** What an item of delivered equipment is (numeral 5.7 a to c). */
enum EquipmentKind: string
{
    case Chair = 'chair';
    case Supplies = 'supplies';
    case ErgonomicAccessory = 'ergonomic_accessory';
    case Computer = 'computer';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Chair => 'Silla ergonómica',
            self::Supplies => 'Insumos',
            self::ErgonomicAccessory => 'Aditamento ergonómico',
            self::Computer => 'Equipo de cómputo',
            self::Other => 'Otro',
        };
    }
}
