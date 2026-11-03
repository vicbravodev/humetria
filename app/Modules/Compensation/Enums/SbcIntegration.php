<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Enums;

/** Whether a benefit forms part of the salario base de cotización (the base of IMSS and Infonavit). */
enum SbcIntegration: string
{
    case Always = 'always';
    case Never = 'never';
    case UpToCap = 'up_to_cap';

    public function label(): string
    {
        return match ($this) {
            self::Always => 'Siempre',
            self::Never => 'Nunca',
            self::UpToCap => 'Hasta un tope',
        };
    }
}
