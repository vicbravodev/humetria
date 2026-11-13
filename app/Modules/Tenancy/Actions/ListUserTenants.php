<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

final class ListUserTenants
{
    /**
     * The companies the user belongs to, by trade name, a page at a time; `$only` narrows them to one company (the one an
     * impersonation started from).
     *
     * One row more than the page is asked for: when it does not come, the first page already holds them all and its size is
     * the total, so a person with a few companies costs one query, not two.
     *
     * @return LengthAwarePaginator<int, Tenant>
     */
    public function handle(int $userId, int $perPage = 25, ?string $only = null): LengthAwarePaginator
    {
        $query = Tenant::query()
            ->whereIn('id', TenantMembership::query()->where('user_id', $userId)->select('tenant_id'))
            ->when($only !== null, fn ($companies) => $companies->where('id', $only))
            ->orderBy('trade_name')
            ->orderBy('id');
        $page = Paginator::resolveCurrentPage();
        $rows = $query->offset(($page - 1) * $perPage)->limit($perPage + 1)->get();
        $total = $page === 1 && $rows->count() <= $perPage ? $rows->count() : $query->toBase()->getCountForPagination();

        return (new LengthAwarePaginator($rows->take($perPage)->values(), $total, $perPage, $page, ['path' => Paginator::resolveCurrentPath()]))->withQueryString();
    }
}
