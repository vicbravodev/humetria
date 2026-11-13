<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Telework\Actions\OpenPortal;
use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Telework\Data\PortalIdentity;
use App\Modules\Telework\Exceptions\PortalUnavailable;
use Illuminate\Http\Request;

/**
 * The portal link of NOM-037 offered to other tools: the same rules as OpenPortal, not a copy of them.
 */
final readonly class TokenPortalAccess implements PortalAccess
{
    public function __construct(private OpenPortal $openPortal, private IssuePortalLink $issue, private Request $request) {}

    /**
     * Opens the link, or reuses what the middleware of this request already opened (see `stopped`): a page does not open
     * its link a second time. Outside that, every call reads the link as it is now.
     */
    public function open(string $token): ?PortalIdentity
    {
        $kept = $this->request->attributes->get($this->key($token));

        $portal = $kept instanceof OpenedPortal ? $kept : $this->read($token);

        return $portal === null ? null : $this->identity($portal);
    }

    public function stopped(string $token, string $module): bool
    {
        $portal = $this->read($token);

        if ($portal === null) {
            return false;
        }

        $this->request->attributes->set($this->key($token), $portal);

        return $portal->summary?->stopped($module) ?? false;
    }

    public function token(string $employeeId): string
    {
        return $this->issue->handle($employeeId);
    }

    private function read(string $token): ?OpenedPortal
    {
        try {
            return $this->openPortal->handle($token);
        } catch (PortalUnavailable) {
            return null;
        }
    }

    private function identity(OpenedPortal $portal): PortalIdentity
    {
        return new PortalIdentity($portal->tenantId, $portal->employeeId, $portal->company, $portal->name);
    }

    private function key(string $token): string
    {
        return 'portal.opened.'.hash('sha256', $token);
    }
}
