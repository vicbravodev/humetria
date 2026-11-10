<?php

declare(strict_types=1);

namespace App\Modules\Leave\Enums;

/**
 * Why a request reached RH (criterio C-18): nobody active above the person, or their manager did not answer in time.
 */
enum EscalationReason: string
{
    case NoManager = 'no_manager';
    case Timeout = 'timeout';
}
