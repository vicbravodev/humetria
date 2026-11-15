<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support;

use App\Modules\Telework\Contracts\PortalSection;
use App\Modules\Telework\Data\PortalIdentity;
use App\Modules\Telework\Data\PortalSectionLink;
use App\Modules\Tenancy\Contracts\Companies;

/**
 * «Nos dijeron / Haremos» in the home of the portal, once the company published what it will do after the climate survey.
 */
final readonly class ClimatePortalSection implements PortalSection
{
    public function __construct(private Companies $companies, private PortalClimatePages $pages) {}

    public function for(PortalIdentity $person, string $token): ?PortalSectionLink
    {
        if ($this->companies->current()->stopped('climate') || $this->pages->survey() === null) {
            return null;
        }

        return new PortalSectionLink('climate', 'Nos dijeron / Haremos', 'Lo que su empresa escuchó en la encuesta de clima y lo que hará.', route('portal.climate.show', $token));
    }
}
