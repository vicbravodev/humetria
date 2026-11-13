<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Modules\Demos\Contracts\DemoLeads;
use App\Modules\Tenancy\Data\TenantData;
use App\Modules\Tenancy\Exceptions\DemoRequestUnavailable;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Creates a company and links the demo request it comes from, or neither. Of two people converting the same request, the
 * second one's company is rolled back and told which company the request already became.
 */
final readonly class CreateTenantFromDemo
{
    public function __construct(private CreateTenant $createTenant, private DemoLeads $leads) {}

    public function handle(TenantData $data, string $demoRequestId): Tenant
    {
        $lead = $this->leads->find($demoRequestId) ?? throw new DemoRequestUnavailable('Esa solicitud de demo ya no existe.');

        $tenant = DB::transaction(function () use ($data, $lead): Tenant {
            $tenant = $this->createTenant->handle($data);

            return $this->leads->markConverted($lead->id, $tenant->id) ? $tenant : throw new DemoRequestUnavailable($this->alreadyConverted($lead->id));
        });

        Log::info('tenant.created_from_demo', ['tenant_id' => $tenant->id, 'demo_request_id' => $lead->id]);

        return $tenant;
    }

    private function alreadyConverted(string $demoRequestId): string
    {
        $tenantId = $this->leads->find($demoRequestId)?->tenantId;
        $name = $tenantId === null ? null : Tenant::query()->find($tenantId, ['id', 'trade_name'])?->trade_name;

        return $name === null ? 'Esta solicitud ya se convirtió en una empresa' : "Esta solicitud ya se convirtió en {$name}";
    }
}
