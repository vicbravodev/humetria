<?php

declare(strict_types=1);

namespace App\Modules\Telework\Enums;

/**
 * Draft: the Comisión is still editing it, nobody answers it. Published: in use; to change it, a new version is made.
 */
enum ChecklistStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
