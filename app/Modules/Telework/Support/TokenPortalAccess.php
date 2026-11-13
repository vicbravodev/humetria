<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Telework\Actions\OpenPortal;
use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Telework\Data\PortalIdentity;
use App\Modules\Telework\Exceptions\PortalUnavailable;
use App\Modules\Tenancy\Data\CompanySummary;

/**
 * The portal link of NOM-037 offered to other tools: the same rules as OpenPortal, not a copy of them.
 */
final class TokenPortalAccess implements PortalAccess
{
    /** @var array<string, ?PortalIdentity> */
    private array $opened = [];

    /** @var array<string, ?CompanySummary> */
    private array $companies = [];

    public function __construct(private readonly OpenPortal $openPortal, private readonly IssuePortalLink $issue) {}

    /**
     * Bound as scoped: the middleware and the page of one request open the same link once.
     */
    public function open(string $token): ?PortalIdentity
    {
        if (array_key_exists($token, $this->opened)) {
            return $this->opened[$token];
        }

        try {
            $portal = $this->openPortal->handle($token);
        } catch (PortalUnavailable) {
            $this->companies[$token] = null;

            return $this->opened[$token] = null;
        }

        $this->companies[$token] = $portal->summary;

        return $this->opened[$token] = new PortalIdentity($portal->tenantId, $portal->employeeId, $portal->company, $portal->name);
    }

    public function stopped(string $token, string $module): bool
    {
        $this->open($token);

        return ($this->companies[$token] ?? null)?->stopped($module) ?? false;
    }

    public function token(string $employeeId): string
    {
        return $this->issue->handle($employeeId);
    }
}
