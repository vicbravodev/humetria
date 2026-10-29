<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Exceptions\NoCurrentTenant;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Context;

/**
 * The company of the current request. ResolveTenant sets it; the rest of the app reads it as CurrentTenant.
 * It lives in Laravel's Context, which is per request and travels inside every job the request queues,
 * so a job works in the company that dispatched it. The same key tags each log line with the company.
 */
final class TenantContext implements CurrentTenant
{
    private const string KEY = 'tenant_id';

    public function set(Tenant $tenant): void
    {
        Context::add(self::KEY, $tenant->id);
    }

    public function id(): string
    {
        $id = Context::get(self::KEY);

        return is_string($id) ? $id : throw new NoCurrentTenant;
    }
}
