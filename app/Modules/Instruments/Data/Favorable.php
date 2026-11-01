<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Data;

/**
 * The answer to a checklist question that does not flag a risk. Informative questions describe the place and never flag.
 */
enum Favorable: string
{
    case Yes = 'yes';
    case No = 'no';
    case Informative = 'informative';
}
