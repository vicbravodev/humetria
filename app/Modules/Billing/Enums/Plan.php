<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

/** What a company contracts. Its prices, centers and quotas live in the catalog (config/billing.php), never here. */
enum Plan: string
{
    case Jornada = 'jornada';
    case Cumplimiento = 'cumplimiento';
    case Integral = 'integral';
    /** A union's: a flat monthly fee for Compensación alone. Only a union can hold it, and a union can hold nothing else. */
    case Sindicato = 'sindicato';

    public function label(): string
    {
        return match ($this) {
            self::Jornada => 'Jornada',
            self::Cumplimiento => 'Cumplimiento',
            self::Integral => 'Integral',
            self::Sindicato => 'Sindicato',
        };
    }
}
