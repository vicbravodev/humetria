<?php

declare(strict_types=1);

namespace App\Modules\Telework\Enums;

/** Where the person stands about an item delivered to them. */
enum EquipmentStatus: string
{
    case Pending = 'pending';
    case Received = 'received';
    case Refused = 'refused';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Por confirmar',
            self::Received => 'Recibido',
            self::Refused => 'Rechazado',
        };
    }
}
