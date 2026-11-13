<?php

declare(strict_types=1);

namespace App\Modules\Demos\Support;

use App\Actions\RecordAudit;
use App\Modules\Demos\Actions\ListDemoRequests;
use App\Modules\Demos\Contracts\DemoLeads;
use App\Modules\Demos\Data\DemoLead;
use App\Modules\Demos\Enums\DemoStatus;
use App\Modules\Demos\Models\DemoRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final readonly class DemoLeadRegistry implements DemoLeads
{
    public function __construct(private RecordAudit $recordAudit, private ListDemoRequests $list) {}

    public function find(string $id): ?DemoLead
    {
        $request = Str::isUuid($id) ? DemoRequest::query()->find($id) : null;

        return $request === null ? null : new DemoLead($request->id, $request->company, $request->name, $request->email, $request->tenant_id);
    }

    public function forCompanies(array $tenantIds): array
    {
        $leads = [];

        foreach (DemoRequest::query()->whereIn('tenant_id', $tenantIds)->get() as $request) {
            if ($request->tenant_id !== null) {
                $leads[$request->tenant_id] = new DemoLead($request->id, $request->company, $request->name, $request->email, $request->tenant_id);
            }
        }

        return $leads;
    }

    public function markConverted(string $id, string $tenantId): bool
    {
        // One conditional UPDATE: of two people converting the same request at once, only one finds it still without a company.
        $changed = DemoRequest::query()->whereKey($id)->whereNull('tenant_id')->update([
            'status' => DemoStatus::Converted->value,
            'tenant_id' => $tenantId,
            'handled_at' => DB::raw('coalesce(handled_at, now())'),
        ]);

        if ($changed === 0) {
            return false;
        }

        Log::info('demo.converted', ['demo_request_id' => $id, 'tenant_id' => $tenantId]);
        $request = DemoRequest::query()->findOrFail($id);
        $this->recordAudit->handle('demo.converted', $request, ['tenant_id' => $tenantId]);

        return true;
    }

    public function countsByStatus(): array
    {
        return $this->list->counts();
    }
}
