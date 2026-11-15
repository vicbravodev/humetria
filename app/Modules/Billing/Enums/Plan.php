<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

/** What a company contracts. Its prices, centers and quotas live in the catalog (config/billing.php), never here. */
enum Plan: string
{
    case Jornada = 'jornada';
    case Cumplimiento = 'cumplimiento';
    case Integral = 'integral';

    public function label(): string
    {
        return match ($this) {
            self::Jornada => 'Jornada',
            self::Cumplimiento => 'Cumplimiento',
            self::Integral => 'Integral',
        };
    }
}
