<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Actions\RecordAudit;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class ReactivateTenant
{
    public function __construct(private RecordAudit $recordAudit) {}

    /**
     * Lifts the restriction, for a reason. The trial and the term are left as they were, so the company comes back to
     * whatever its dates say: a reactivation never grants time.
     */
    public function handle(Tenant $tenant, string $reason): Tenant
    {
        return DB::transaction(function () use ($tenant, $reason): Tenant {
            $previous = $tenant->restriction?->value;

            $tenant->update(['restriction' => null, 'restriction_reason' => null, 'restricted_at' => null]);

            Log::info('tenant.reactivated', ['tenant_id' => $tenant->id, 'previous' => $previous]);
            $this->recordAudit->handle('tenant.reactivated', $tenant, ['reason' => $reason, 'previous' => $previous]);

            return $tenant;
        });
    }
}
