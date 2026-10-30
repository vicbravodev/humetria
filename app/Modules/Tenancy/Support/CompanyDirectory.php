<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Data\CompanySummary;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class CompanyDirectory implements Companies
{
    public function __construct(private TenantContext $tenantContext) {}

    public function enter(string $tenantId): CompanySummary
    {
        $tenant = Str::isUuid($tenantId) ? Tenant::query()->find($tenantId) : null;

        if ($tenant === null) {
            throw new InvalidArgumentException("No company [{$tenantId}].");
        }

        $this->tenantContext->set($tenant);

        return new CompanySummary($tenant->id, $tenant->trade_name, $tenant->status()->isBlocked());
    }
}
