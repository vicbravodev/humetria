<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Telework\Enums\TeleworkerStatus;
use App\Modules\Telework\Exceptions\PortalUnavailable;
use App\Modules\Telework\Models\PortalLink;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Support\OpenedPortal;
use App\Modules\Tenancy\Contracts\Companies;

/**
 * Opens the portal of the person whose link carries `$token`, inside their company. The link works while it is the
 * latest one issued (RH did not revoke it, see `IssuePortalLink`) and a kept app key derives it, the person is still an
 * active employee and the company may be used.
 */
final readonly class OpenPortal
{
    public function __construct(private Companies $companies, private Roster $roster) {}

    /**
     * @throws PortalUnavailable
     */
    public function handle(string $token): OpenedPortal
    {
        // Read past the company scope: until the link is known, no company is.
        $link = PortalLink::query()->withoutGlobalScopes()->where('token_hash', PortalLink::hashToken($token))->first();

        // The hash finds the row; a key still kept must derive it (a link of a dropped key is revoked).
        if ($link === null || $link->token() !== $token) {
            throw new PortalUnavailable;
        }

        $company = $this->companies->enter($link->tenant_id);
        $person = $this->roster->find($link->employee_id);

        if ($company->blocked || $person === null || ! $person->active) {
            throw new PortalUnavailable;
        }

        $teleworker = Teleworker::query()->where('employee_id', $person->id)->where('status', '!=', TeleworkerStatus::Ended)->first(['id']);

        return new OpenedPortal($company->id, $person->id, $teleworker?->id, $company->tradeName, $person->name);
    }
}
