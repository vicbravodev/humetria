<?php

declare(strict_types=1);

namespace App\Modules\Demos\Enums;

/**
 * Who asks for the demo: a company that applies the NOM-035 itself, or a consultant who applies it for clients.
 */
enum DemoProfile: string
{
    case Company = 'company';
    case Consultant = 'consultant';

    public function label(): string
    {
        return match ($this) {
            self::Company => 'Empresa',
            self::Consultant => 'Consultor',
        };
    }
}
