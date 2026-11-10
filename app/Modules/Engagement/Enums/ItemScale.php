<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Enums;

/** How a question is answered: five points of agreement, 0 to 10 for the recommendation, or free text. */
enum ItemScale
{
    case Agreement;
    case Recommend;
    case Open;

    /** @return array{int, int}|null */
    public function range(): ?array
    {
        return match ($this) {
            self::Agreement => [1, 5],
            self::Recommend => [0, 10],
            self::Open => null,
        };
    }
}
