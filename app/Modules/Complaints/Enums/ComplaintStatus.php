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

    /** For a count of them: «6 En revisión». */
    public function pluralLabel(): string
    {
        return match ($this) {
            self::New => 'Nuevas',
            self::InReview => 'En revisión',
            self::Closed => 'Cerradas',
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
