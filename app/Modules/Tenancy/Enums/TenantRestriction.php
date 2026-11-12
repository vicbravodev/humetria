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

    /** The log and audit event of applying it. */
    public function event(): string
    {
        return match ($this) {
            self::ReadOnly => 'tenant.set_read_only',
            self::Suspended => 'tenant.suspended',
            self::Archived => 'tenant.archived',
        };
    }

    public function confirmation(): string
    {
        return match ($this) {
            self::ReadOnly => 'Empresa en solo lectura.',
            self::Suspended => 'Empresa suspendida.',
            self::Archived => 'Empresa archivada.',
        };
    }

    public function status(): TenantStatus
    {
        return match ($this) {
            self::ReadOnly => TenantStatus::ReadOnly,
            self::Suspended => TenantStatus::Suspended,
            self::Archived => TenantStatus::Archived,
        };
    }
}
