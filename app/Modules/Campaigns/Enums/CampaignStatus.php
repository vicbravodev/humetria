<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Enums;

/**
 * Draft: being prepared, nobody invited. Active: launched, links work until its deadline. Closed: no link works.
 */
enum CampaignStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Active => 'En curso',
            self::Closed => 'Cerrada',
        };
    }
}
