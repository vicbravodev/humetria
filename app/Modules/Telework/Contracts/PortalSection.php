<?php

declare(strict_types=1);

namespace App\Modules\Telework\Contracts;

use App\Modules\Telework\Data\PortalIdentity;
use App\Modules\Telework\Data\PortalSectionLink;

/**
 * What a tool offers a person in the home of their portal. Each module implements one and tags it `portal.sections` in its
 * provider; the home lists whatever answers a link, in a fixed order, and never learns what a tool is.
 */
interface PortalSection
{
    /**
     * The link of the tool for this person; null when the company does not have the tool or there is nothing for them in it.
     */
    public function for(PortalIdentity $person, string $token): ?PortalSectionLink;
}
