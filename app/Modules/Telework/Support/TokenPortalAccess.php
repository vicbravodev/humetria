<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Telework\Actions\OpenPortal;
use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Telework\Data\PortalIdentity;
use App\Modules\Telework\Exceptions\PortalUnavailable;

/**
 * The portal link of NOM-037 offered to other tools: the same rules as OpenPortal, not a copy of them.
 */
final readonly class TokenPortalAccess implements PortalAccess
{
    public function __construct(private OpenPortal $openPortal) {}

    public function open(string $token): ?PortalIdentity
    {
        try {
            $portal = $this->openPortal->handle($token);
        } catch (PortalUnavailable) {
            return null;
        }

        return new PortalIdentity($portal->tenantId, $portal->employeeId, $portal->company, $portal->name);
    }
}
