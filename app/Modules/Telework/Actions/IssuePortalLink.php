<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Modules\Telework\Models\PortalLink;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Gives an employee of the current company a personal portal link, replacing the one they had (which stops working).
 * Only the hash is kept: the plain token goes to the caller once and is never logged.
 */
final readonly class IssuePortalLink
{
    public function __construct(private CurrentTenant $tenant) {}

    public function handle(string $employeeId): string
    {
        $token = Str::random(64);

        PortalLink::query()->updateOrCreate(
            ['employee_id' => $employeeId],
            ['tenant_id' => $this->tenant->id(), 'token_hash' => PortalLink::hashToken($token)],
        );
        Log::info('telework.portal_link_issued', ['employee_id' => $employeeId]);

        return $token;
    }
}
