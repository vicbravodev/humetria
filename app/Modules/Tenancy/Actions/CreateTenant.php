<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Actions\RecordAudit;
use App\Modules\Tenancy\Data\TenantData;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class CreateTenant
{
    public function __construct(private RecordAudit $recordAudit) {}

    public function handle(TenantData $data): Tenant
    {
        return DB::transaction(function () use ($data): Tenant {
            $tenant = Tenant::query()->create([
                'legal_name' => $data->legalName,
                'trade_name' => $data->tradeName,
                'rfc' => $data->rfc,
                'slug' => $data->slug,
            ]);

            // Only the id: the names and the RFC (a person's, when the company is a sole proprietor) stay out of logs and audit.
            Log::info('tenant.created', ['tenant_id' => $tenant->id]);
            $this->recordAudit->handle('tenant.created', $tenant);

            return $tenant;
        });
    }
}
