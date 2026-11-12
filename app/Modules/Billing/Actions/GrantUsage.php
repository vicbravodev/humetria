<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Actions\RecordAudit;
use App\Models\User;
use App\Modules\Billing\Models\UsageGrant;
use App\Modules\Billing\Support\UsagePeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class GrantUsage
{
    public function __construct(private RecordAudit $recordAudit) {}

    /**
     * Adds tokens to the company's quota for the current month, with the reason. Next month the plan's quota alone applies again.
     */
    public function handle(string $tenantId, string $meter, int $quantity, string $reason, User $grantedBy): UsageGrant
    {
        return DB::transaction(function () use ($tenantId, $meter, $quantity, $reason, $grantedBy): UsageGrant {
            $period = UsagePeriod::now();
            $grant = UsageGrant::query()->create([
                'tenant_id' => $tenantId,
                'meter' => $meter,
                'period_start' => $period->toDateString(),
                'quantity' => $quantity,
                'reason' => $reason,
                'granted_by' => $grantedBy->id,
                'created_at' => now(),
            ]);

            Log::info('billing.usage_granted', ['tenant_id' => $tenantId, 'meter' => $meter, 'quantity' => $quantity]);
            $this->recordAudit->handle('billing.usage_granted', $grant, [
                'tenant_id' => $tenantId,
                'meter' => $meter,
                'quantity' => $quantity,
                'reason' => $reason,
                'period_start' => $period->toDateString(),
            ]);

            return $grant;
        });
    }
}
