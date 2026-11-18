<?php

declare(strict_types=1);

namespace App\Modules\Sso\Actions;

use App\Actions\RecordAudit;
use App\Modules\Sso\Models\SsoConnection;
use Illuminate\Support\Facades\Log;

/**
 * Turns single sign-on on or off for a company, and requires it of everyone but the owner or not. Turning it off lifts the
 * requirement: nobody is left needing a provider that is not there. The request checked there is a proven domain.
 */
final readonly class SetSsoState
{
    public function __construct(private RecordAudit $audit) {}

    public function handle(string $tenantId, bool $enabled, bool $enforced): void
    {
        $connection = SsoConnection::query()->where('tenant_id', $tenantId)->firstOrFail();
        $enforced = $enabled && $enforced;
        $before = ['enabled' => $connection->enabled, 'enforced' => $connection->enforced];

        $connection->update(['enabled' => $enabled, 'enforced' => $enforced]);

        $events = [];

        if ($enabled !== $before['enabled']) {
            $events[] = $enabled ? 'sso.enabled' : 'sso.disabled';
        }

        if ($enforced !== $before['enforced']) {
            $events[] = $enforced ? 'sso.enforced' : 'sso.enforcement_lifted';
        }

        foreach ($events as $event) {
            Log::info($event, ['tenant_id' => $tenantId]);
            $this->audit->handle($event, null, ['tenant_id' => $tenantId]);
        }
    }
}
