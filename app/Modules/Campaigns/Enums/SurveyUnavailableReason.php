<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Enums;

/**
 * Why a survey link no longer opens the survey. An invalid link names no company: anyone can type a URL.
 */
enum SurveyUnavailableReason: string
{
    case Invalid = 'invalid';
    case Closed = 'closed';
    case Done = 'done';
    case Suspended = 'suspended';
}
