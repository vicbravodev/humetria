<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Exceptions\NoCurrentTenant;
use App\Modules\Tenancy\Models\Tenant;

/**
 * Holds the company of the current request. ResolveTenant sets it; the rest of the app reads it as CurrentTenant.
 * Scoped to the request (see TenancyServiceProvider), so it never carries over to the next one.
 */
final class TenantContext implements CurrentTenant
{
    private ?string $id = null;

    public function set(Tenant $tenant): void
    {
        $this->id = $tenant->id;
    }

    public function id(): string
    {
        return $this->id ?? throw new NoCurrentTenant;
    }
}
