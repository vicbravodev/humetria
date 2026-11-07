<?php

declare(strict_types=1);

namespace App\Modules\Demos\Enums;

/**
 * Who asks for the demo: a company or group that uses Humetría for itself, a consultant who uses it with clients,
 * or a union that wants Compensación for its side of the table.
 */
enum DemoProfile: string
{
    case Company = 'company';
    case Consultant = 'consultant';
    case Union = 'union';

    public function label(): string
    {
        return match ($this) {
            self::Company => 'Empresa',
            self::Consultant => 'Consultor',
            self::Union => 'Sindicato',
        };
    }
}
