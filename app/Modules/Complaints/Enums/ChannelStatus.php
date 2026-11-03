<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Enums;

enum ChannelStatus: string
{
    case Inactive = 'inactive';
    case Active = 'active';
    case Paused = 'paused';

    public function label(): string
    {
        return match ($this) {
            self::Inactive => 'Sin activar',
            self::Active => 'Activo',
            self::Paused => 'En pausa',
        };
    }
}
