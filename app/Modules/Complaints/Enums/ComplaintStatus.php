<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Enums;

enum ComplaintStatus: string
{
    case New = 'new';
    case InReview = 'in_review';
    case Closed = 'closed';

    /** How the person who presented it is told where it stands. */
    public function reporterLabel(): string
    {
        return match ($this) {
            self::New => 'Recibida',
            self::InReview => 'En revisión',
            self::Closed => 'Cerrada',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nueva',
            self::InReview => 'En revisión',
            self::Closed => 'Cerrada',
        };
    }
}
