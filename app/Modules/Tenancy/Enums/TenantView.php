<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Enums;

/**
 * The views of the console's companies: all of them, or those in one status. Expired gathers a lapsed payment and an
 * ended trial: both are blocked until the superadmin activates them.
 */
enum TenantView: string
{
    case All = 'all';
    case Active = 'active';
    case Trial = 'trial';
    case Expired = 'expired';
    case Deactivated = 'deactivated';
}
