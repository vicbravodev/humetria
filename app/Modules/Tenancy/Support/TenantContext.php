<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Exceptions\NoCurrentTenant;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Context;
use Spatie\Permission\PermissionRegistrar;

/**
 * The company of the current request. ResolveTenant sets it; the rest of the app reads it as CurrentTenant.
 * It lives in Laravel's Context, which is per request and travels inside every job the request queues, so a job works
 * in the company that dispatched it; the same key tags each log line. It is also Spatie's team, so roles and
 * permissions are read inside the company.
 */
final readonly class TenantContext implements CurrentTenant
{
    private const string KEY = 'tenant_id';

    public function __construct(private PermissionRegistrar $registrar) {}

    public function set(Tenant $tenant): void
    {
        Context::add(self::KEY, $tenant->id);
        $this->registrar->setPermissionsTeamId($tenant->id);
    }

    public function id(): string
    {
        $id = Context::get(self::KEY);

        return is_string($id) ? $id : throw new NoCurrentTenant;
    }
}
