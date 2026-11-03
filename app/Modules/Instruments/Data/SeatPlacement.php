<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Data;

/**
 * Where the seat goes, the three results (A, B and C) of Figura 1 of the Disposiciones of 2025-07-17.
 */
enum SeatPlacement: string
{
    case AtWorkstation = 'at_workstation';
    case Nearby = 'nearby';
    case WithinCenter = 'within_center';
}
