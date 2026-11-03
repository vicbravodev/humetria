<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Enums;

/**
 * The Spatie roles a user can have inside a company (its team). What each allows lives in Spatie's permissions:
 * manage-organization (owner, hr) and manage-members (owner); a viewer only looks; the committee (Comisión de seguridad e higiene) sees only NOM-037, Ley Silla and the complaints channel; the handler of complaints
 * (an outsider: a lawyer, a trusted person) only the channel.
 */
enum TenantRole: string
{
    case Owner = 'owner';
    case Hr = 'hr';
    case Viewer = 'viewer';
    case Committee = 'committee';
    case Complaints = 'complaints';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Dueño',
            self::Hr => 'RH',
            self::Viewer => 'Consulta',
            self::Committee => 'Comisión de seguridad e higiene',
            self::Complaints => 'Atención de quejas',
        };
    }

    /** How the invitation link page says what the person is joining as. */
    public function invitedAs(string $tradeName): string
    {
        return match ($this) {
            self::Owner => "Te invitaron como owner de {$tradeName}",
            self::Hr => "Te invitaron como RH de {$tradeName}",
            self::Viewer => "Te invitaron a consultar {$tradeName}",
            self::Committee => "Te invitaron a la Comisión de seguridad e higiene de {$tradeName}",
            self::Complaints => "Te invitaron a atender el canal de quejas de {$tradeName}",
        };
    }

    /**
     * The route names a restricted role may open inside a company (LimitRestrictedRoles); null when the role is not restricted.
     *
     * @return list<string>|null
     */
    public function allowedRoutes(): ?array
    {
        return match ($this) {
            self::Committee => ['tenant.telework.*', 'tenant.seating.*', 'tenant.complaints.*'],
            self::Complaints => ['tenant.complaints.*'],
            self::Owner, self::Hr, self::Viewer => null,
        };
    }

    /** Where a person of the role starts: the panel, or the tool a restricted role is limited to. */
    public function startRoute(): string
    {
        return match ($this) {
            self::Committee => 'tenant.telework.index',
            self::Complaints => 'tenant.complaints.index',
            self::Owner, self::Hr, self::Viewer => 'tenant.dashboard',
        };
    }
}
