<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListTenants
{
    /**
     * @return LengthAwarePaginator<int, Tenant>
     */
    public function handle(): LengthAwarePaginator
    {
        return Tenant::query()->with(['ownerMembership.user', 'openInvitation'])->latest()->paginate(25);
    }
}
