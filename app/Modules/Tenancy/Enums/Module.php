<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Enums;

/**
 * What a company can have on or off: the keys of the catalog's modules (config/billing.php). Four of them are only names so far,
 * because their tools are not built (vacations, org chart, pulse, climate): they have no routes and no jobs to switch.
 */
enum Module: string
{
    case Attendance = 'attendance';
    case Vacations = 'vacations';
    case OrgChart = 'org_chart';
    case Nom035 = 'nom035';
    case Nom037 = 'nom037';
    case Seating = 'seating';
    case Complaints = 'complaints';
    case Pulse = 'pulse';
    case Climate = 'climate';

    public function label(): string
    {
        return match ($this) {
            self::Attendance => 'Registro de jornada',
            self::Vacations => 'Vacaciones',
            self::OrgChart => 'Organigramas',
            self::Nom035 => 'NOM-035',
            self::Nom037 => 'NOM-037',
            self::Seating => 'Ley Silla',
            self::Complaints => 'Buzón de quejas',
            self::Pulse => 'Pulso',
            self::Climate => 'Clima laboral',
        };
    }

    /** Whether the tool exists in the code today, so a company without a plan keeps it. */
    public function existsInCode(): bool
    {
        return match ($this) {
            self::Attendance, self::Nom035, self::Nom037, self::Seating, self::Complaints => true,
            self::Vacations, self::OrgChart, self::Pulse, self::Climate => false,
        };
    }
}
