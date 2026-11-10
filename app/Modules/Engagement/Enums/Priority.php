<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Enums;

/** How urgent it is to act on a driver. There is no "low": a driver that does not move the engagement is not listed. */
enum Priority: string
{
    case High = 'high';
    case Medium = 'medium';

    public function label(): string
    {
        return match ($this) {
            self::High => 'Alta',
            self::Medium => 'Media',
        };
    }
}
