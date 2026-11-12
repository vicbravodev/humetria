<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Actions\RecordAudit;
use App\Modules\Billing\Data\RenewalSummary;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Exceptions\InvalidStatement;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * The cut (day 1, calendar month in the display time zone). Ends the cancellations scheduled for it, drafts the month of every
 * company that pays through the same actions as the console (never issuing: a person reviews each draft), and moves each
 * renewal a month on. It can run again: what is done is found done. A company that cannot be drafted is skipped with its reason.
 */
final readonly class RenewSubscriptions
{
    public function __construct(private GenerateStatement $generate, private RecordAudit $recordAudit, private ApplyScheduledChanges $applyChanges) {}

    public function handle(?CarbonImmutable $on = null): RenewalSummary
    {
        // The month is the one of the display time zone's calendar; its first day is kept as a plain date (midnight UTC), like every date of the statements.
        $start = CarbonImmutable::parse(($on ?? CarbonImmutable::now())->setTimezone(config()->string('app.display_timezone'))->startOfMonth()->toDateString());
        $period = $start->format('Y-m');
        [$drafted, $already, $ended, $skipped, $accounts] = [0, 0, 0, [], []];

        // First what waited for the cut (reductions), so the month is drafted at the plan the company has from today.
        $this->applyChanges->handle($on);

        foreach (Subscription::query()->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue, SubscriptionStatus::CancelScheduled])->orderBy('id')->get() as $subscription) {
            if ($subscription->status === SubscriptionStatus::CancelScheduled && $subscription->renews_on->lessThanOrEqualTo($start)) {
                $this->end($subscription, $period);
                $ended++;

                continue;
            }

            if ($subscription->starts_on->greaterThanOrEqualTo($start->addMonth())) {
                continue;
            }

            $existing = Statement::query()->where('tenant_id', $subscription->tenant_id)->where('period_start', $start->toDateString())->whereNull('adjusts_statement_id')->first();

            try {
                $statement = $existing ?? $this->generate->handle($subscription->tenant_id, $start, null);
            } catch (InvalidStatement $exception) {
                $skipped[] = ['tenant_id' => $subscription->tenant_id, 'reason' => $exception->getMessage()];
                Log::warning('billing.renewal_skipped', ['tenant_id' => $subscription->tenant_id, 'period' => $period, 'reason' => $exception->getMessage()]);

                continue;
            }

            $existing === null ? $drafted++ : $already++;
            $accounts[$statement->billing_account_id] = [
                'account_id' => $statement->billing_account_id,
                'drafts' => ($accounts[$statement->billing_account_id]['drafts'] ?? 0) + 1,
                'total_cents' => ($accounts[$statement->billing_account_id]['total_cents'] ?? 0) + $statement->total_cents,
            ];

            if ($subscription->renews_on->lessThan($start->addMonth())) {
                $subscription->update(['renews_on' => $start->addMonth()]);
            }
        }

        Log::info('billing.renewal_run', ['period' => $period, 'drafted' => $drafted, 'already_drafted' => $already, 'ended' => $ended, 'skipped' => count($skipped)]);

        return new RenewalSummary($period, $drafted, $already, $ended, $skipped, array_values($accounts));
    }

    private function end(Subscription $subscription, string $period): void
    {
        $subscription->update(['status' => SubscriptionStatus::Ended]);
        Log::info('billing.subscription_ended', ['tenant_id' => $subscription->tenant_id, 'period' => $period]);
        $this->recordAudit->handle('billing.subscription_ended', $subscription, ['tenant_id' => $subscription->tenant_id, 'period' => $period]);
    }
}
