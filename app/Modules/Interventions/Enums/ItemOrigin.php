<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Enums;

/**
 * Where a row came from: derived from the results of the evaluation, or added by HR (a preventive action, num. 8.1 and 8.2,
 * or a row of a sample under the minimum). A manual row has no level and does not count toward what the Tabla asks.
 */
enum ItemOrigin: string
{
    case Results = 'results';
    case Manual = 'manual';
}
