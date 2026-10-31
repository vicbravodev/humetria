<?php

declare(strict_types=1);

namespace App\Modules\Telework\Enums;

/** Why a telework period ended. */
enum EndReason: string
{
    case Reversibility = 'reversibility';
    case LeftCompany = 'left_company';
    case Transfer = 'transfer';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Reversibility => 'Reversibilidad',
            self::LeftCompany => 'Salió de la empresa',
            self::Transfer => 'Cambio de centro de trabajo',
            self::Other => 'Otro motivo',
        };
    }
}
