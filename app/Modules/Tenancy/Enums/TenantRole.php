<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Enums;

/**
 * What a user is inside a company. The owner runs it and manages its people's access; hr works its work centers
 * and employees; a viewer only looks at work centers (and, later, aggregated results).
 */
enum TenantRole: string
{
    case Owner = 'owner';
    case Hr = 'hr';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Hr => 'RH',
            self::Viewer => 'Consulta',
        };
    }

    /** Work centers and employees: add, correct, archive, and see employees' contact data. */
    public function canManageOrganization(): bool
    {
        return $this !== self::Viewer;
    }

    /** Invite people, change their role and remove their access. */
    public function canManageMembers(): bool
    {
        return $this === self::Owner;
    }
}
