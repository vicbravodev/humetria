<?php

declare(strict_types=1);

namespace App\Modules\Telework\Enums;

/** Section I (the first verification, on registering) or Section II (the periodic one) of the checklist. */
enum Section: int
{
    case One = 1;
    case Two = 2;
}
