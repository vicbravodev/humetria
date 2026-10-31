<?php

declare(strict_types=1);

namespace App\Modules\Telework\Enums;

/** Where a row of the inspection table 10.3 stands in a work center. */
enum RowState: string
{
    case Done = 'done';
    case Missing = 'missing';
    case Partial = 'partial';
    case NotApplicable = 'not_applicable';

    public function label(): string
    {
        return match ($this) {
            self::Done => 'Cumple',
            self::Missing => 'Falta',
            self::Partial => 'Incompleto',
            self::NotApplicable => 'No aplica',
        };
    }
}
