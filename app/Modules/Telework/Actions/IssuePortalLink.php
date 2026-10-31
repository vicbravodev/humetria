<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Modules\Telework\Models\PortalLink;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Gives an employee of the current company a personal portal link, replacing the one they had. The replaced one stops
 * working at once (RH asked for a new link, often because the old one leaked or was lost), unless `$graceForPrevious`:
 * then it keeps working for GRACE_DAYS, so a reminder never breaks a list the person has half answered. Only the latest
 * replaced link is kept. Only hashes are kept: the plain token goes to the caller once and is never logged.
 */
final readonly class IssuePortalLink
{
    public const int GRACE_DAYS = 7;

    public function __construct(private CurrentTenant $tenant) {}

    public function handle(string $employeeId, bool $graceForPrevious = false): string
    {
        $token = Str::random(64);
        $current = $graceForPrevious ? PortalLink::query()->where('employee_id', $employeeId)->first(['token_hash']) : null;

        PortalLink::query()->updateOrCreate(
            ['employee_id' => $employeeId],
            [
                'tenant_id' => $this->tenant->id(),
                'token_hash' => PortalLink::hashToken($token),
                'previous_token_hash' => $current?->token_hash,
                'previous_valid_until' => $current === null ? null : now()->addDays(self::GRACE_DAYS),
            ],
        );
        Log::info('telework.portal_link_issued', ['employee_id' => $employeeId]);

        return $token;
    }
}
