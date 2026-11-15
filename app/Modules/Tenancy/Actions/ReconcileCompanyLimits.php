<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Data\LimitCorrection;
use App\Modules\Tenancy\Models\TenantLimit;
use App\Modules\Tenancy\Support\RealUsage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class ReconcileCompanyLimits
{
    public function __construct(private RealUsage $usage) {}

    /**
     * Recounts what every limited company really has and sets the counters that drifted (a crash between the reservation and the
     * insert cannot happen, but a restore, a manual fix or a bug could). Each counter is read under its row's lock, so a request
     * that reserved a place and is still writing it is waited for, not undone.
     *
     * @return list<LimitCorrection>
     */
    public function handle(): array
    {
        $corrections = [];

        foreach (TenantLimit::query()->orderBy('tenant_id')->orderBy('resource')->get(['id']) as $row) {
            DB::transaction(function () use ($row, &$corrections): void {
                $limit = TenantLimit::query()->lockForUpdate()->find($row->id);

                if ($limit === null) {
                    return;
                }

                $actual = $this->usage->of($limit->tenant_id, $limit->resource);

                if ($actual === $limit->used) {
                    return;
                }

                $correction = new LimitCorrection($limit->tenant_id, $limit->resource, $limit->used, $actual);
                $limit->update(['used' => $actual]);
                Log::warning('tenant.limit_reconciled', ['tenant_id' => $correction->tenantId, 'resource' => $correction->resource, 'recorded' => $correction->recorded, 'actual' => $correction->actual]);
                $corrections[] = $correction;
            });
        }

        return $corrections;
    }
}
