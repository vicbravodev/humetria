<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use Illuminate\Database\Eloquent\Collection;

final class ListUserTenants
{
    /**
     * The companies the user belongs to, by trade name.
     *
     * @return Collection<int, Tenant>
     */
    public function handle(int $userId): Collection
    {
        return Tenant::query()
            ->whereIn('id', TenantMembership::query()->where('user_id', $userId)->select('tenant_id'))
            ->orderBy('trade_name')
            ->get();
    }
}
