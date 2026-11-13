<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Telework\Contracts\PortalSection;
use App\Modules\Telework\Data\PortalIdentity;
use App\Modules\Telework\Data\PortalSectionLink;
use App\Modules\Tenancy\Contracts\Companies;

/**
 * «Mis vacaciones» in the home of the portal; for a manager it says how many requests of their team wait for an answer.
 */
final readonly class VacationsPortalSection implements PortalSection
{
    public function __construct(private Companies $companies) {}

    public function for(PortalIdentity $person, string $token): ?PortalSectionLink
    {
        if ($this->companies->current()->stopped('vacations') || ! LeaveSetting::query()->exists()) {
            return null;
        }

        $waiting = PortalTeamPages::pendingFor($person->employeeId);

        return new PortalSectionLink(
            'vacations',
            'Mis vacaciones',
            $waiting === 0 ? 'Su saldo de días, sus solicitudes y sus permisos.' : 'Su saldo, sus solicitudes y las de su equipo que esperan su respuesta.',
            route('portal.vacations.show', $token),
            $waiting === 0 ? null : $waiting,
        );
    }
}
