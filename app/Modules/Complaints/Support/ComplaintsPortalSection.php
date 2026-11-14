<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use App\Modules\Complaints\Contracts\ComplaintChannels;
use App\Modules\Telework\Contracts\PortalSection;
use App\Modules\Telework\Data\PortalIdentity;
use App\Modules\Telework\Data\PortalSectionLink;
use App\Modules\Tenancy\Contracts\Companies;

/**
 * «Quejas y denuncias» in the home of the portal: the way to the public channel of the company while it takes complaints.
 * The link is the channel's own, the same for everybody: nothing of the person or of their portal travels in it, so the
 * channel keeps no way to know who came from where (criterio C-46).
 */
final readonly class ComplaintsPortalSection implements PortalSection
{
    public function __construct(private Companies $companies, private ComplaintChannels $channels) {}

    public function for(PortalIdentity $person, string $token): ?PortalSectionLink
    {
        $url = $this->channels->current()->url;

        // The company is read only when the channel takes complaints.
        if ($url === null || $this->companies->current()->stopped('complaints')) {
            return null;
        }

        return new PortalSectionLink('complaints', 'Quejas y denuncias', 'Cuéntenos de forma confidencial lo que no está bien en su trabajo. Puede hacerlo sin dar su nombre.', $url);
    }
}
