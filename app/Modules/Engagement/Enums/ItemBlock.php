<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Enums;

/** The part of the survey a question belongs to. */
enum ItemBlock
{
    case Outcome;
    case Company;
    case Manager;
    case Closing;
}
