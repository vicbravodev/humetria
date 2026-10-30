<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Enums;

/**
 * The Spatie roles a user can have inside a company (its team). What each allows lives in Spatie's permissions:
 * manage-organization (owner, hr) and manage-members (owner); a viewer only looks.
 */
enum TenantRole: string
{
    case Owner = 'owner';
    case Hr = 'hr';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Dueño',
            self::Hr => 'RH',
            self::Viewer => 'Consulta',
        };
    }

    /** How the invitation link page says what the person is joining as. */
    public function invitedAs(string $tradeName): string
    {
        return match ($this) {
            self::Owner => "Te invitaron como owner de {$tradeName}",
            self::Hr => "Te invitaron como RH de {$tradeName}",
            self::Viewer => "Te invitaron a consultar {$tradeName}",
        };
    }
}
