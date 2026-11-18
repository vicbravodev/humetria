<?php

declare(strict_types=1);

namespace App\Modules\Sso\Actions;

use App\Actions\RecordAudit;
use App\Modules\Sso\Data\SsoConnectionData;
use App\Modules\Sso\Models\SsoConnection;
use App\Modules\Sso\Models\SsoDomain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Saves the identity provider of a company and the domains it claims. A domain that stays keeps its proof; one that goes takes it
 * away; a new one starts with a token of its own. A company left with no proven domain cannot keep single sign-on on.
 */
final readonly class SaveSsoConnection
{
    public function __construct(private RecordAudit $audit) {}

    public function handle(string $tenantId, SsoConnectionData $data): SsoConnection
    {
        $connection = DB::transaction(function () use ($tenantId, $data): SsoConnection {
            $connection = SsoConnection::query()->where('tenant_id', $tenantId)->first() ?? new SsoConnection(['tenant_id' => $tenantId]);
            $connection->fill(['issuer' => $data->issuer, 'client_id' => $data->clientId]);

            if ($data->clientSecret !== null) {
                $connection->client_secret = $data->clientSecret;
            }

            $connection->save();

            SsoDomain::query()->where('tenant_id', $tenantId)->whereNotIn('domain', $data->domains)->delete();

            foreach ($data->domains as $domain) {
                SsoDomain::query()->firstOrCreate(['tenant_id' => $tenantId, 'domain' => $domain], ['token' => Str::random(40)]);
            }

            if (! SsoDomain::query()->where('tenant_id', $tenantId)->whereNotNull('verified_at')->exists()) {
                $connection->update(['enabled' => false, 'enforced' => false]);
            }

            return $connection;
        });

        $context = ['tenant_id' => $tenantId, 'issuer' => $data->issuer, 'client_id' => $data->clientId, 'domains' => $data->domains];
        Log::info('sso.configured', $context);
        $this->audit->handle('sso.configured', null, $context);

        return $connection;
    }
}
