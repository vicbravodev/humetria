<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Enums;

/** How a scenario raises the salary of each group: a percent of it, or a fixed amount a month (decision A13). */
enum RaiseUnit: string
{
    case Percent = 'percent';
    case Amount = 'amount';
}
