<?php

declare(strict_types=1);

namespace App\Modules\Leave\Enums;

/**
 * What a row of a vacation policy gives: the days of a range of years of service (never under the law), or days that
 * come every year on top, such as a birthday or personal days (criterio C-14).
 */
enum RowKind: string
{
    case Tier = 'tier';
    case Fixed = 'fixed';
}
