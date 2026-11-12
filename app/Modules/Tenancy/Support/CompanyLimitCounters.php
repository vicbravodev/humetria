<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Billing\Contracts\PlanSteps;
use App\Modules\Tenancy\Actions\ReconcileCompanyLimits;
use App\Modules\Tenancy\Actions\SyncCompanyLimits;
use App\Modules\Tenancy\Contracts\CompanyLimits;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Data\LimitUsage;
use App\Modules\Tenancy\Models\TenantLimit;
use Illuminate\Validation\ValidationException;

final readonly class CompanyLimitCounters implements CompanyLimits
{
    public function __construct(
        private CurrentTenant $currentTenant,
        private PlanSteps $steps,
        private SyncCompanyLimits $sync,
        private ReconcileCompanyLimits $reconcile,
    ) {}

    public function reserve(string $resource, int $quantity, string $field): void
    {
        if ($quantity <= 0) {
            return;
        }

        // One statement decides: the row is locked by the UPDATE itself, so of two requests for the last place the second finds it taken.
        $taken = TenantLimit::query()
            ->where('tenant_id', $this->currentTenant->id())
            ->where('resource', $resource)
            ->whereRaw('"used" + ? <= "amount"', [$quantity])
            ->increment('used', $quantity);

        if ($taken === 0) {
            $this->refuseIfLimited($resource, $quantity, $field);
        }
    }

    public function release(string $resource, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $row = TenantLimit::query()->where('tenant_id', $this->currentTenant->id())->where('resource', $resource);

        // Never below zero: a counter that is short (it drifted) goes to zero instead of failing the person who removes someone.
        (clone $row)->where('used', '>=', $quantity)->decrement('used', $quantity);
        (clone $row)->where('used', '<', $quantity)->update(['used' => 0]);
    }

    public function ensureRoom(string $resource, int $quantity, string $field): void
    {
        $limit = $this->ofCurrent($resource);

        if ($limit !== null && $limit->used + $quantity > $limit->amount) {
            $this->refuse($limit, $quantity, $field);
        }
    }

    public function usage(string $tenantId): array
    {
        $rows = TenantLimit::query()->where('tenant_id', $tenantId)->get()->keyBy('resource');
        $usage = [];

        foreach (RealUsage::RESOURCES as $resource) {
            $row = $rows->get($resource);

            if ($row !== null) {
                $usage[] = new LimitUsage($resource, RealUsage::label($resource), $row->used, $row->amount, $row->used > $row->amount);
            }
        }

        return $usage;
    }

    public function limit(string $tenantId, string $resource): ?LimitUsage
    {
        return array_find($this->usage($tenantId), fn (LimitUsage $usage): bool => $usage->resource === $resource);
    }

    public function sync(string $tenantId, array $amounts): void
    {
        $this->sync->handle($tenantId, $amounts);
    }

    public function reconcile(): array
    {
        return $this->reconcile->handle();
    }

    /** The statement took nothing: either the company has no limit on it, or it is full. */
    private function refuseIfLimited(string $resource, int $quantity, string $field): void
    {
        $limit = $this->ofCurrent($resource);

        if ($limit !== null) {
            $this->refuse($limit, $quantity, $field);
        }
    }

    private function ofCurrent(string $resource): ?TenantLimit
    {
        return TenantLimit::query()->where('tenant_id', $this->currentTenant->id())->where('resource', $resource)->first();
    }

    private function refuse(TenantLimit $limit, int $quantity, string $field): never
    {
        $step = $this->steps->describe($limit->tenant_id, $limit->resource, $limit->used + $quantity);

        throw ValidationException::withMessages([$field => LimitMessage::refusal($limit->resource, $limit->amount, $limit->used, $quantity, $step)]);
    }
}
