<?php

declare(strict_types=1);

namespace App\Modules\Demos\Enums;

/**
 * Headcount ranges aligned with the NOM-035 thresholds: up to 15 (Guía I), 16 to 50 (Guía II), more than 50 (Guía III).
 */
enum Workforce: string
{
    case UpTo15 = '1_15';
    case From16To50 = '16_50';
    case From51To100 = '51_100';
    case From101To500 = '101_500';
    case MoreThan500 = '501_plus';
}
