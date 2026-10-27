<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Actions\RecordAudit;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class DeactivateTenant
{
    public function __construct(private RecordAudit $recordAudit) {}

    /**
     * Blocks the company until the superadmin activates it again. Nothing is deleted.
     */
    public function handle(Tenant $tenant): Tenant
    {
        return DB::transaction(function () use ($tenant): Tenant {
            $tenant->update(['deactivated_at' => now()]);

            Log::info('tenant.deactivated', ['tenant_id' => $tenant->id]);
            $this->recordAudit->handle('tenant.deactivated', $tenant);

            return $tenant;
        });
    }
}
