<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Models\TenantLimit;
use App\Modules\Tenancy\Support\RealUsage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final readonly class SyncCompanyLimits
{
    public function __construct(private RealUsage $usage) {}

    /**
     * Sets what the plan contracts. A resource without a limit yet starts counting from what the company has; one that had it keeps
     * its count, so a company already above its new limit is not touched: it only cannot add.
     *
     * @param  array<string, int>  $amounts  by resource.
     *
     * @throws InvalidArgumentException
     */
    public function handle(string $tenantId, array $amounts): void
    {
        foreach ($amounts as $resource => $amount) {
            if (! in_array($resource, RealUsage::RESOURCES, true) || $amount < 0) {
                throw new InvalidArgumentException("[{$resource}] cannot be limited to [{$amount}].");
            }
        }

        DB::transaction(function () use ($tenantId, $amounts): void {
            foreach ($amounts as $resource => $amount) {
                $limit = TenantLimit::query()->where('tenant_id', $tenantId)->where('resource', $resource)->lockForUpdate()->first();

                if ($limit === null) {
                    TenantLimit::query()->create(['tenant_id' => $tenantId, 'resource' => $resource, 'amount' => $amount, 'used' => $this->usage->of($tenantId, $resource)]);
                } else {
                    $limit->update(['amount' => $amount]);
                }
            }

            Log::info('tenant.limits_synced', ['tenant_id' => $tenantId, 'amounts' => $amounts]);
        });
    }
}
