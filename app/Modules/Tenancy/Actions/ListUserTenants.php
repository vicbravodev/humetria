<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListUserTenants
{
    /**
     * The companies the user belongs to, by trade name, a page at a time.
     *
     * @return LengthAwarePaginator<int, Tenant>
     */
    public function handle(int $userId, int $perPage = 25): LengthAwarePaginator
    {
        return Tenant::query()
            ->whereIn('id', TenantMembership::query()->where('user_id', $userId)->select('tenant_id'))
            ->orderBy('trade_name')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
