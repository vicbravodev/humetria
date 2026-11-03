<?php

declare(strict_types=1);

namespace App\Modules\Seating\Enums;

/**
 * The life of the internal regulation: drafted, signed by the comisión mixta, deposited and distributed (LFT 424 and 425; criterio C-13). Each step follows the previous one.
 */
enum RegulationStatus: string
{
    case Draft = 'draft';
    case Signed = 'signed';
    case Deposited = 'deposited';
    case Distributed = 'distributed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Signed => 'Firmado',
            self::Deposited => 'Depositado',
            self::Distributed => 'Repartido',
        };
    }
}
