<?php

declare(strict_types=1);

namespace App\Modules\Sso\Actions;

use App\Modules\Sso\Models\SsoConnection;
use App\Modules\Sso\Models\SsoDomain;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Contracts\CompanyRegistry;

/**
 * The connection a company's people may sign in with right now: its plan has the module, a person has not suspended the
 * company, the owner switched it on and a domain is proven. Null says there is none, whatever the reason: a page that said why
 * would tell a stranger what a company has.
 */
final readonly class OpenSsoSignIn
{
    public function __construct(private CompanyRegistry $companies, private CompanyModules $modules) {}

    public function handle(string $tenantId): ?SsoConnection
    {
        $company = $this->companies->find($tenantId);

        if ($company === null || $company->withdrawn || ! $this->modules->enabled($tenantId, 'sso')) {
            return null;
        }

        $connection = SsoConnection::query()->where('tenant_id', $tenantId)->where('enabled', true)->first();
        $proven = SsoDomain::query()->where('tenant_id', $tenantId)->whereNotNull('verified_at')->exists();

        return $proven ? $connection : null;
    }
}
