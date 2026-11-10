<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Enums;

/** What a result can be cut by, one at a time. */
enum CutAttribute: string
{
    case WorkCenter = 'work_center';
    case Area = 'area';
    case Tenure = 'tenure';
    case Manager = 'manager';
}
