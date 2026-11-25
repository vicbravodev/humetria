<?php

declare(strict_types=1);

namespace App\Modules\Demos\Enums;

/**
 * Where a demo request stands in the funnel. «Demo agendada» is reached by scheduling it with its day, «Convertida» only by
 * creating the company from it; «Prueba activa» is set by hand.
 */
enum DemoStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Scheduled = 'scheduled';
    case Trial = 'trial';
    case Converted = 'converted';
    case Discarded = 'discarded';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nueva',
            self::Contacted => 'Contactada',
            self::Scheduled => 'Demo agendada',
            self::Trial => 'Prueba activa',
            self::Converted => 'Convertida',
            self::Discarded => 'Descartada',
        };
    }
}
