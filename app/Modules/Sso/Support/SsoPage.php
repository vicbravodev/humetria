<?php

declare(strict_types=1);

namespace App\Modules\Sso\Support;

use App\Modules\Sso\Models\SsoConnection;
use App\Modules\Sso\Models\SsoDomain;

/**
 * What the owner's page of single sign-on shows. The secret never goes: only that there is one.
 */
final class SsoPage
{
    /**
     * @return array{connection: array{issuer: string, client_id: string, has_secret: bool, enabled: bool, enforced: bool}|null, domains: list<array{id: string, domain: string, verified: bool, record_name: string, record_value: string}>}
     */
    public function handle(string $tenantId): array
    {
        $connection = SsoConnection::query()->where('tenant_id', $tenantId)->first();

        return [
            'connection' => $connection === null ? null : [
                'issuer' => $connection->issuer,
                'client_id' => $connection->client_id,
                'has_secret' => $connection->client_secret !== '',
                'enabled' => $connection->enabled,
                'enforced' => $connection->enforced,
            ],
            'domains' => array_values(SsoDomain::query()->where('tenant_id', $tenantId)->orderBy('domain')->get()->map(fn (SsoDomain $domain): array => [
                'id' => $domain->id,
                'domain' => $domain->domain,
                'verified' => $domain->verified_at !== null,
                'record_name' => $domain->recordName(),
                'record_value' => $domain->recordValue(),
            ])->all()),
        ];
    }
}
