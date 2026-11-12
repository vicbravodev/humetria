<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Actions\RecordAudit;
use App\Modules\Tenancy\Enums\TenantRestriction;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class RestrictTenant
{
    public function __construct(private RecordAudit $recordAudit) {}

    /**
     * Puts the company in read-only, suspended or archived, for a reason, until a person reactivates it. Nothing is
     * deleted and no date or balance changes: only the column that says how far its access goes.
     */
    public function handle(Tenant $tenant, TenantRestriction $restriction, string $reason): Tenant
    {
        return DB::transaction(function () use ($tenant, $restriction, $reason): Tenant {
            $previous = $tenant->restriction?->value;

            $tenant->update(['restriction' => $restriction, 'restriction_reason' => $reason, 'restricted_at' => now()]);

            Log::info($restriction->event(), ['tenant_id' => $tenant->id, 'previous' => $previous]);
            $this->recordAudit->handle($restriction->event(), $tenant, ['reason' => $reason, 'previous' => $previous]);

            return $tenant;
        });
    }
}
