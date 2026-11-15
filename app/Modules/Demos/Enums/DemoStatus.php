<?php

declare(strict_types=1);

namespace App\Modules\Demos\Enums;

/** Where a demo request stands. «Convertida» is only reached by creating the company from it. */
enum DemoStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Converted = 'converted';
    case Discarded = 'discarded';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nueva',
            self::Contacted => 'Contactada',
            self::Converted => 'Convertida',
            self::Discarded => 'Descartada',
        };
    }
}
