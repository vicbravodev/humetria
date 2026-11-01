<?php

declare(strict_types=1);

namespace App\Modules\Telework\Enums;

/**
 * The months between applications of Section II of the checklist that the panel offers as choices (criterio C-14);
 * a company may also type its own, from MIN to MAX months.
 */
enum ReviewPeriod: int
{
    case Quarterly = 3;
    case Biannual = 6;
    case Annual = 12;
    case Biennial = 24;

    public const int MIN = 1;

    public const int MAX = 60;

    public const self DEFAULT = self::Annual;

    /** @return list<int> */
    public static function presets(): array
    {
        return array_map(fn (self $period): int => $period->value, self::cases());
    }
}
