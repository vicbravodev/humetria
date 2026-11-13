<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Disclosure\Enums\PageLinkStatus;
use App\Modules\Disclosure\Models\PageLink;
use App\Modules\Telework\Contracts\PortalSection;
use App\Modules\Telework\Data\PortalIdentity;
use App\Modules\Telework\Data\PortalSectionLink;
use App\Modules\Tenancy\Contracts\Companies;

/**
 * «Avisos y resultados de su centro» in the home of the portal: the way to the page of the people, while its link is active.
 */
final readonly class CenterPortalSection implements PortalSection
{
    public function __construct(private Companies $companies) {}

    public function for(PortalIdentity $person, string $token): ?PortalSectionLink
    {
        if ($this->companies->current()->stopped('nom035')) {
            return null;
        }

        $link = PageLink::query()->latest('version')->first();
        $url = $link === null || $link->status() !== PageLinkStatus::Active ? null : PageUrl::of($link);

        return $url === null ? null : new PortalSectionLink('center', 'Avisos y resultados de su centro', 'La política, las medidas y los resultados que su empresa publica para su centro.', $url);
    }
}
