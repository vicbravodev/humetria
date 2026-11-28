<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Log;

/** Says who at Humetría answers for several companies at once, or that nobody does. The demo company is nobody's customer. */
final class AssignAccountManager
{
    /**
     * @param  list<string>  $tenantIds
     * @return int how many companies changed.
     */
    public function handle(array $tenantIds, ?User $manager): int
    {
        $changed = Tenant::query()->whereIn('id', $tenantIds)->where('is_demo', false)->update(['owner_user_id' => $manager?->id]);

        Log::info('tenancy.account_manager_assigned', ['companies' => $changed, 'manager_id' => $manager?->id]);

        return $changed;
    }
}
