<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Telework\Contracts\PortalSection;
use App\Modules\Telework\Data\PortalIdentity;
use App\Modules\Telework\Data\PortalSectionLink;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Contracts\WorkerCredentials;

/**
 * «Mi jornada» in the home of the portal, for a company with the registro de jornada: marking, «Mi semana» and the linking
 * of the phone. While the person has no phone linked, the linking is the one thing waiting for them.
 */
final readonly class AttendancePortalSection implements PortalSection
{
    public function __construct(private Companies $companies, private WorkerCredentials $credentials) {}

    public function for(PortalIdentity $person, string $token): ?PortalSectionLink
    {
        if (! $this->companies->current()->allows('attendance')) {
            return null;
        }

        $linked = ($this->credentials->countsByEmployee([$person->employeeId])[$person->employeeId]->active ?? 0) > 0;

        return new PortalSectionLink('attendance', 'Mi jornada', 'Marque su entrada y su salida, vea su semana y vincule su teléfono.', route('portal.attendance.show', $token), $linked ? null : 1);
    }
}
