<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\AccountKind;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Exceptions\InvalidStatement;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\BillingNotice;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Notifications\EligibilityLostNotice;
use App\Modules\Billing\Notifications\NextAmountNotice;
use App\Modules\Billing\Notifications\OverdueBalanceNotice;
use App\Modules\Billing\Support\Catalog;
use App\Modules\Billing\Support\Money;
use App\Modules\Billing\Support\ShortDate;
use App\Modules\Billing\Support\StatementBalances;
use App\Modules\Tenancy\Contracts\CompanyRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * The notices of the cut, by email to the account's contact, once each: what each company will be charged next month and that a
 * consultant is about to lose the discount (both from five days before the cut), and a balance still open after the days to pay.
 * It only tells: it suspends nothing and changes no plan. Run again, it finds what it already said.
 */
final readonly class SendBillingNotices
{
    public function __construct(private BuildStatement $build, private CompanyRegistry $companies, private StatementBalances $balances) {}

    /** @return int notices sent */
    public function handle(?CarbonImmutable $on = null): int
    {
        $now = $on ?? CarbonImmutable::now();
        $local = $now->setTimezone(config()->string('app.display_timezone'));
        $next = CarbonImmutable::parse($local->startOfMonth()->addMonth()->toDateString());
        $sent = 0;

        if ($local->toDateString() >= $next->subDays(config()->integer('billing.notice_days_before_cut'))->toDateString()) {
            foreach ($this->accountsWithCompanies() as $accountId => $tenantIds) {
                $account = BillingAccount::query()->findOrFail($accountId);
                $sent += (int) $this->nextAmount($account, $tenantIds, $next);
                $sent += (int) $this->eligibilityLost($account, $tenantIds, $next);
            }
        }

        return $sent + $this->overdue($now);
    }

    /** @return array<string, list<string>> the companies each account pays now. */
    private function accountsWithCompanies(): array
    {
        $byAccount = [];

        foreach (AccountCompany::query()->current()->orderBy('billing_account_id')->get(['billing_account_id', 'tenant_id']) as $link) {
            $byAccount[$link->billing_account_id][] = $link->tenant_id;
        }

        return $byAccount;
    }

    /** @param  list<string>  $tenantIds */
    private function nextAmount(BillingAccount $account, array $tenantIds, CarbonImmutable $next): bool
    {
        $names = $this->names($tenantIds);
        $lines = [];

        foreach (Subscription::query()->whereIn('tenant_id', $tenantIds)->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue, SubscriptionStatus::CancelScheduled])->orderBy('id')->get() as $subscription) {
            if ($subscription->status === SubscriptionStatus::CancelScheduled && $subscription->renews_on->lessThanOrEqualTo($next)) {
                continue;
            }

            try {
                $lines[] = ['company' => $names[$subscription->tenant_id] ?? '', 'amount' => Money::format($this->build->handle($subscription->tenant_id, $next)->totalCents)];
            } catch (InvalidStatement) {
                continue;
            }
        }

        usort($lines, fn (array $a, array $b): int => $a['company'] <=> $b['company']);

        return $lines !== [] && $this->tell($account, 'next_amount', $next->format('Y-m'), new NextAmountNotice($account->legal_name, $lines, $this->month($next)));
    }

    /** @param  list<string>  $tenantIds */
    private function eligibilityLost(BillingAccount $account, array $tenantIds, CarbonImmutable $next): bool
    {
        if ($account->kind !== AccountKind::Consultant) {
            return false;
        }

        $minimum = Catalog::current()->integralMinimumCompanies();
        $paying = Subscription::query()->whereIn('tenant_id', $tenantIds)->where('plan', Plan::Integral)->get()->filter(fn (Subscription $subscription): bool => $subscription->status->isPaying());
        $leaving = $paying->filter(fn (Subscription $subscription): bool => $subscription->status === SubscriptionStatus::CancelScheduled && $subscription->renews_on->lessThanOrEqualTo($next));

        if ($paying->count() < $minimum || $paying->count() - $leaving->count() >= $minimum) {
            return false;
        }

        $names = $this->names($tenantIds);

        return $this->tell($account, 'eligibility_lost', $next->format('Y-m'), new EligibilityLostNotice($account->legal_name, array_values($leaving->map(fn (Subscription $subscription): string => $names[$subscription->tenant_id] ?? '')->sort()->all()), $minimum, $this->month($next)));
    }

    private function overdue(CarbonImmutable $now): int
    {
        $due = Statement::query()->overdue($now)
            ->whereRaw('statements.id::text NOT IN (SELECT reference FROM billing_notices WHERE kind = ?)', ['overdue'])->orderBy('period_start')->orderBy('id')->get();
        $paid = $this->balances->paid(array_values($due->map(fn (Statement $statement): string => $statement->id)->all()));
        $names = $this->names(array_values(array_unique($due->map(fn (Statement $statement): string => $statement->tenant_id)->all())));
        $sent = 0;

        foreach ($due->groupBy('billing_account_id') as $accountId => $statements) {
            $account = BillingAccount::query()->findOrFail($accountId);
            $lines = array_values($statements->map(fn (Statement $statement): array => [
                'company' => $names[$statement->tenant_id] ?? '',
                'period' => ShortDate::month($statement->period_start),
                'balance' => Money::format($statement->total_cents - ($paid[$statement->id] ?? 0)),
            ])->all());

            $sent += (int) $this->tellStatements($account, $statements, new OverdueBalanceNotice($account->legal_name, $lines));
        }

        return $sent;
    }

    /** @param  Collection<int, Statement>  $statements */
    private function tellStatements(BillingAccount $account, Collection $statements, BaseNotification $notice): bool
    {
        return DB::transaction(function () use ($account, $statements, $notice): bool {
            foreach ($statements as $statement) {
                BillingNotice::query()->insert(['id' => (string) Str::uuid(), 'billing_account_id' => $account->id, 'kind' => 'overdue', 'reference' => $statement->id, 'sent_at' => now()]);
            }

            return $this->send($account, 'overdue', $notice);
        });
    }

    private function tell(BillingAccount $account, string $kind, string $reference, BaseNotification $notice): bool
    {
        return DB::transaction(function () use ($account, $kind, $reference, $notice): bool {
            $new = BillingNotice::query()->insertOrIgnore(['id' => (string) Str::uuid(), 'billing_account_id' => $account->id, 'kind' => $kind, 'reference' => $reference, 'sent_at' => now()]);

            return $new === 1 && $this->send($account, $kind, $notice);
        });
    }

    private function send(BillingAccount $account, string $kind, BaseNotification $notice): bool
    {
        Notification::route('mail', $account->contact_email)->notify($notice);
        Log::info('billing.notice_sent', ['account_id' => $account->id, 'kind' => $kind]);

        return true;
    }

    /**
     * @param  list<string>  $tenantIds
     * @return array<string, string> trade names by company id.
     */
    private function names(array $tenantIds): array
    {
        return array_map(fn ($brief): string => $brief->tradeName, $this->companies->many($tenantIds));
    }

    private function month(CarbonImmutable $month): string
    {
        return $month->isoFormat('MMMM [de] YYYY');
    }
}
