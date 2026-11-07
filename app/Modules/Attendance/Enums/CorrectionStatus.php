<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Enums;

/** Where a person's request to correct their record stands: waiting for RH, approved (a correction mark) or rejected. */
enum CorrectionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
