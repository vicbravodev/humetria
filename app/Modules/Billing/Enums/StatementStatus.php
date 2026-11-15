<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

/**
 * Where a statement of account stands as a document. Whether it is paid is not here: it is derived from the payments
 * applied to it, so an issued statement never changes.
 */
enum StatementStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Issued => 'Emitido',
        };
    }
}
