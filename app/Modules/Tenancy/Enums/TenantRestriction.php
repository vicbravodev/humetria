<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Enums;

/**
 * What a person decided about a company's access, whatever its dates say. Never set by a date: always with a reason.
 */
enum TenantRestriction: string
{
    case ReadOnly = 'read_only';
    case Suspended = 'suspended';
    case Archived = 'archived';

    public function status(): TenantStatus
    {
        return match ($this) {
            self::ReadOnly => TenantStatus::ReadOnly,
            self::Suspended => TenantStatus::Suspended,
            self::Archived => TenantStatus::Archived,
        };
    }
}
