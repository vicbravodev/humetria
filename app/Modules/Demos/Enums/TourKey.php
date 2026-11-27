<?php

declare(strict_types=1);

namespace App\Modules\Demos\Enums;

/** A tour of a guided session: a module the demo can show. «base» (the center and its people) always goes first, unchosen. */
enum TourKey: string
{
    case Base = 'base';
    case Nom035 = 'nom035';
    case Attendance = 'attendance';
    case Engagement = 'engagement';
    case Complaints = 'complaints';
    case Leave = 'leave';
    case Humi = 'humi';
}
