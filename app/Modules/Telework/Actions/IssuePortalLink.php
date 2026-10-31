<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Modules\Telework\Models\PortalLink;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The portal link of an employee of the current company. It is derived (see `PortalLink`), so issuing it again — an
 * invitation, a reminder — gives the same link and never breaks the one the person has open. `$revoke` (RH's «Generar
 * enlace», often because the link leaked or was lost) moves the version: the previous link stops working at once. A link
 * whose key was dropped from the app keys is issued again with the current one. Only its hash is kept; the plain token
 * goes to the caller and is never logged.
 */
final readonly class IssuePortalLink
{
    public function __construct(private CurrentTenant $tenant) {}

    public function handle(string $employeeId, bool $revoke = false): string
    {
        return DB::transaction(function () use ($employeeId, $revoke): string {
            // `createOrFirst`: if two first issues race, the loser reads the winner's row instead of failing on the unique index.
            $tenantId = $this->tenant->id();
            $created = PortalLink::query()->createOrFirst(
                ['employee_id' => $employeeId],
                ['tenant_id' => $tenantId, 'version' => 1, 'token_hash' => PortalLink::hashToken(PortalLink::tokenFor($tenantId, $employeeId, 1, config()->string('app.key')))],
            );
            $isNew = $created->wasRecentlyCreated;
            $link = PortalLink::query()->whereKey($created->id)->lockForUpdate()->firstOrFail();

            if ($revoke && ! $isNew) {
                $link->version++;
            }

            $token = (! $isNew && ! $revoke ? $link->token() : null)
                ?? PortalLink::tokenFor($link->tenant_id, $employeeId, $link->version, config()->string('app.key'));
            $link->token_hash = PortalLink::hashToken($token);
            $link->save();

            Log::info('telework.portal_link_issued', ['employee_id' => $employeeId]);

            if ($revoke && $link->version > 1) {
                Log::info('telework.portal_link_revoked', ['employee_id' => $employeeId, 'version' => $link->version]);
            }

            return $token;
        });
    }
}
