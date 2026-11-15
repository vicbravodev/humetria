<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Actions\RecordAudit;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\Catalog;
use App\Modules\Tenancy\Contracts\CompanyLimits;
use App\Modules\Tenancy\Contracts\CompanyModules;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * At the cut, the reductions that waited (a smaller plan, fewer people, fewer centers) become the plan: the same writes as when a
 * plan is set (its limits and its modules follow), no credit for the days before. What was applied is cleared, so a run again
 * finds nothing to do.
 */
final readonly class ApplyScheduledChanges
{
    public function __construct(private RecordAudit $recordAudit, private CompanyModules $modules, private CompanyLimits $limits) {}

    /** @return int how many subscriptions changed. */
    public function handle(?CarbonImmutable $on = null): int
    {
        $today = CarbonImmutable::parse(($on ?? CarbonImmutable::now())->setTimezone(config()->string('app.display_timezone'))->toDateString());
        $applied = 0;

        foreach (Subscription::query()->whereNotNull('pending_effective_on')->where('pending_effective_on', '<=', $today->toDateString())->orderBy('id')->get(['id']) as $due) {
            $applied += DB::transaction(fn (): int => $this->apply($due->id, $today));
        }

        return $applied;
    }

    private function apply(string $id, CarbonImmutable $today): int
    {
        $subscription = Subscription::query()->whereKey($id)->lockForUpdate()->first();

        if ($subscription === null || $subscription->pending_effective_on === null) {
            return 0;
        }

        $effectiveOn = $subscription->pending_effective_on;
        $subscription->fill([
            'plan' => $subscription->pending_plan ?? $subscription->plan,
            'profile' => $subscription->pending_profile ?? $subscription->profile,
            'seats' => $subscription->pending_seats ?? $subscription->seats,
            'centers' => $subscription->pending_centers ?? $subscription->centers,
            'pending_plan' => null,
            'pending_profile' => null,
            'pending_seats' => null,
            'pending_centers' => null,
            'pending_effective_on' => null,
        ])->save();

        $this->modules->syncPlan($subscription->tenant_id, Catalog::current()->modules($subscription->plan), $today);
        $this->limits->sync($subscription->tenant_id, [
            CompanyLimits::SEATS => $subscription->seats,
            CompanyLimits::CENTERS => max($subscription->centers, Catalog::current()->includedCenters($subscription->plan)),
        ]);

        Log::info('billing.subscription_change_applied', ['tenant_id' => $subscription->tenant_id, 'effective_on' => $effectiveOn->toDateString()]);
        $this->recordAudit->handle('billing.subscription_change_applied', $subscription, [
            'tenant_id' => $subscription->tenant_id,
            'plan' => $subscription->plan->value,
            'seats' => $subscription->seats,
            'centers' => $subscription->centers,
            'effective_on' => $effectiveOn->toDateString(),
        ]);

        return 1;
    }
}
