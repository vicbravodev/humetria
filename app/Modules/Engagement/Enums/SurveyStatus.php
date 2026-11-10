<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Enums;

enum SurveyStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Closed = 'closed';
}
