<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Enums;

/** Where a commitment of the company stands. A dropped one frees its place among the three a group may have. */
enum CommitmentStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Dropped = 'dropped';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Por hacer',
            self::InProgress => 'En marcha',
            self::Done => 'Cumplido',
            self::Dropped => 'Descartado',
        };
    }
}
