<?php

declare(strict_types=1);

namespace App\Modules\Demos\Enums;

/**
 * The tools Humetría offers, so a demo request says which one the prospect is after.
 */
enum Tool: string
{
    case Nom035 = 'nom_035';
    case Nom037 = 'nom_037';
    case LeySilla = 'ley_silla';
    case Complaints = 'complaints';
    case Compensation = 'compensation';

    public function label(): string
    {
        return match ($this) {
            self::Nom035 => 'NOM-035',
            self::Nom037 => 'NOM-037',
            self::LeySilla => 'Ley Silla',
            self::Complaints => 'Buzón de quejas',
            self::Compensation => 'Compensación',
        };
    }
}
