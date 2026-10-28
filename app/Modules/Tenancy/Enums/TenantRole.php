<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Enums;

/**
 * What a user is inside a company. Only the owner exists for now.
 */
enum TenantRole: string
{
    case Owner = 'owner';
}
