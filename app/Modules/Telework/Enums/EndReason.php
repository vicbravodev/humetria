<?php

declare(strict_types=1);

namespace App\Modules\Telework\Enums;

/** Why a telework period ended. */
enum EndReason: string
{
    case Reversibility = 'reversibility';
    case LeftCompany = 'left_company';
    case Transfer = 'transfer';
    case Other = 'other';
}
