<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Enums;

enum DocumentStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
