<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Enums;

/** What a pulse looks at besides its fixed questions; exactly one per pulse, never combined. */
enum PulseMode: string
{
    case FollowUp = 'follow_up';
    case Tour = 'tour';
    case Manager = 'manager';

    public function label(): string
    {
        return match ($this) {
            self::FollowUp => 'Seguimiento',
            self::Tour => 'Recorrido',
            self::Manager => 'Jefe',
        };
    }
}
