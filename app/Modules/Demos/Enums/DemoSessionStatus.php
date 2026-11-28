<?php

declare(strict_types=1);

namespace App\Modules\Demos\Enums;

/** Where a demo session stands: the first three hold the one-session lock; ended and failed free it. */
enum DemoSessionStatus: string
{
    case Preparing = 'preparing';
    case Active = 'active';
    case Ending = 'ending';
    case Ended = 'ended';
    case Failed = 'failed';
}
