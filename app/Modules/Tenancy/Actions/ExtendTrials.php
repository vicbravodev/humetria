<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Actions\RecordAudit;
use App\Modules\Tenancy\Enums\TenantStatus;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\OwnerNotices;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Gives more days of trial to the chosen companies: a running trial from its end, an ended one from now. A company that pays, was
 * restricted by a person or never started its trial is left as it is. The trial is counted from its start, so the start moves.
 */
final readonly class ExtendTrials
{
    public function __construct(private RecordAudit $recordAudit, private OwnerNotices $ownerNotices) {}

    /**
     * @param  list<string>  $tenantIds
     * @return int how many trials were extended.
     */
    public function handle(array $tenantIds, int $days): int
    {
        $tenants = Tenant::query()->whereIn('id', $tenantIds)->where('is_demo', false)->get()
            ->filter(fn (Tenant $tenant): bool => in_array($tenant->status(), [TenantStatus::Trial, TenantStatus::TrialEnded], true));

        $reopened = [];

        DB::transaction(function () use ($tenants, $days, &$reopened): void {
            foreach ($tenants as $tenant) {
                $endsAt = $tenant->trialEndsAt();
                $from = $endsAt === null || $endsAt->isPast() ? now()->toImmutable() : $endsAt;
                $tenant->update(['trial_started_at' => $from->addDays($days - Tenant::TRIAL_DAYS)]);
                $this->recordAudit->handle('tenant.trial_extended', $tenant, ['days' => $days, 'ends_at' => $from->addDays($days)->toIso8601String()]);

                if ($endsAt !== null && $endsAt->isPast()) {
                    $reopened[$tenant->id] = $from->addDays($days);
                }
            }
        });

        // Only an ended trial is news to the owner: they had lost the way in. A running one just runs longer.
        foreach ($tenants as $tenant) {
            if (isset($reopened[$tenant->id])) {
                $this->ownerNotices->trialExtended($tenant, $reopened[$tenant->id]);
            }
        }

        Log::info('tenancy.trial_extended', ['companies' => $tenants->count(), 'days' => $days]);

        return $tenants->count();
    }
}
