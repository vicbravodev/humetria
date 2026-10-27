<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Actions\RecordAudit;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class ActivateTenant
{
    public function __construct(private RecordAudit $recordAudit) {}

    /**
     * Activates the company, or reactivates it, until the given moment. The trial dates are left as they were.
     */
    public function handle(Tenant $tenant, CarbonImmutable $until): Tenant
    {
        return DB::transaction(function () use ($tenant, $until): Tenant {
            $tenant->update(['active_until' => $until, 'deactivated_at' => null]);

            $context = ['active_until' => $until->toIso8601String()];

            Log::info('tenant.activated', ['tenant_id' => $tenant->id, ...$context]);
            $this->recordAudit->handle('tenant.activated', $tenant, $context);

            return $tenant;
        });
    }
}
