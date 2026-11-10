<?php

declare(strict_types=1);

namespace App\Modules\Leave\Enums;

/**
 * The days of the week a vacation policy counts as working days (criterio C-04).
 */
enum WorkWeek: string
{
    case MonFri = 'mon_fri';
    case MonSat = 'mon_sat';
}
