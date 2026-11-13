<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Disclosure\Models\Acknowledgment;
use App\Modules\Telework\Contracts\PortalSection;
use App\Modules\Telework\Data\PortalIdentity;
use App\Modules\Telework\Data\PortalSectionLink;
use App\Modules\Tenancy\Contracts\Companies;

/**
 * «Política por confirmar» in the home of the portal, while a policy asked of the person is not confirmed.
 */
final readonly class PolicyPortalSection implements PortalSection
{
    public function __construct(private Companies $companies) {}

    public function for(PortalIdentity $person, string $token): ?PortalSectionLink
    {
        if ($this->companies->current()->stopped('nom035')) {
            return null;
        }

        $waiting = Acknowledgment::query()->where('employee_id', $person->employeeId)->whereNull('acknowledged_at')->count();

        return $waiting === 0 ? null : new PortalSectionLink('policy', 'Política por confirmar', 'Lea la política de su centro y confirme que la leyó.', route('portal.policy.show', $token), $waiting);
    }
}
