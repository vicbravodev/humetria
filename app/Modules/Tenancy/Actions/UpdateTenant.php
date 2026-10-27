<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Actions\RecordAudit;
use App\Modules\Tenancy\Data\TenantData;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class UpdateTenant
{
    public function __construct(private RecordAudit $recordAudit) {}

    public function handle(Tenant $tenant, TenantData $data): Tenant
    {
        return DB::transaction(function () use ($tenant, $data): Tenant {
            $tenant->update([
                'legal_name' => $data->legalName,
                'trade_name' => $data->tradeName,
                'rfc' => $data->rfc,
                'slug' => $data->slug,
            ]);

            $changed = array_keys($tenant->getChanges());
            $changed = array_values(array_diff($changed, ['updated_at']));
            sort($changed);

            if ($changed !== []) {
                // Only the names of the fields: the values (an RFC can be a person's) stay out of logs and audit.
                Log::info('tenant.updated', ['tenant_id' => $tenant->id, 'changed' => $changed]);
                $this->recordAudit->handle('tenant.updated', $tenant, ['changed' => $changed]);
            }

            return $tenant;
        });
    }
}
