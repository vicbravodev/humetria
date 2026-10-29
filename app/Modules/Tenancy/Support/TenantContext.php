<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Exceptions\NoCurrentTenant;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Context;

/**
 * The company of the current request and the person's role in it. ResolveTenant sets them; the rest of the app reads
 * them as CurrentTenant. They live in Laravel's Context, which is per request and travels inside every job the
 * request queues, so a job works in the company that dispatched it. The company also tags each log line; the role
 * stays hidden from logs.
 */
final class TenantContext implements CurrentTenant
{
    private const string KEY = 'tenant_id';

    private const string ROLE = 'tenant_role';

    public function set(Tenant $tenant, TenantRole $role): void
    {
        Context::add(self::KEY, $tenant->id);
        Context::addHidden(self::ROLE, $role->value);
    }

    public function id(): string
    {
        $id = Context::get(self::KEY);

        return is_string($id) ? $id : throw new NoCurrentTenant;
    }

    public function role(): TenantRole
    {
        $role = Context::getHidden(self::ROLE);

        return is_string($role) ? TenantRole::from($role) : throw new NoCurrentTenant;
    }

    public function canManageOrganization(): bool
    {
        return $this->role()->canManageOrganization();
    }

    public function canManageMembers(): bool
    {
        return $this->role()->canManageMembers();
    }
}
