<?php

declare(strict_types=1);

namespace App\Modules\Telework\Enums;

/** The estado civil of numeral 5.1. */
enum MaritalStatus: string
{
    case Single = 'single';
    case Married = 'married';
    case FreeUnion = 'free_union';
    case Separated = 'separated';
    case Divorced = 'divorced';
    case Widowed = 'widowed';
}
