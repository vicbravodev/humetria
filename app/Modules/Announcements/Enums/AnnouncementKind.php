<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Enums;

/** What an announcement is about, which sets its color in the console and in the banner. */
enum AnnouncementKind: string
{
    case News = 'news';
    case Maintenance = 'maintenance';
    case Incident = 'incident';
    case Billing = 'billing';

    public function label(): string
    {
        return match ($this) {
            self::News => 'Novedad',
            self::Maintenance => 'Mantenimiento',
            self::Incident => 'Incidente',
            self::Billing => 'Cobranza',
        };
    }
}
