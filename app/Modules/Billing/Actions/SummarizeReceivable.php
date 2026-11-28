<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Contracts\PlatformRevenue;
use App\Modules\Billing\Contracts\Receivables;
use App\Modules\Billing\Data\CompanyRevenue;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\Money;
use App\Modules\Billing\Support\OpenBalances;
use Carbon\CarbonImmutable;

final readonly class SummarizeReceivable
{
    public function __construct(private OpenBalances $open, private Receivables $receivables, private PlatformRevenue $revenue) {}

    /**
     * What the account owes across its issued statements, how many still have a balance, what of it is past due and how old its
     * oldest open statement is (null when nothing is).
     *
     * @return array{outstanding: string, open_statements: int, overdue: string|null, aging: array{bucket: string, days: int}|null}
     */
    public function handle(BillingAccount $account): array
    {
        $open = $this->open->handle([$account->id]);
        $overdue = array_sum(array_map(fn (array $statement): int => $statement['days'] > 0 ? $statement['balance'] : 0, $open));
        $oldest = $open[0]['days'] ?? null;

        return [
            'outstanding' => Money::format(array_sum(array_map(fn (array $statement): int => $statement['balance'], $open))),
            'open_statements' => count($open),
            'overdue' => $overdue === 0 ? null : Money::format($overdue),
            'aging' => $oldest === null ? null : ['bucket' => OpenBalances::bucket($oldest), 'days' => abs($oldest)],
        ];
    }

    /**
     * What all the accounts owe, split by how long it is past due (each part with its share of the whole, rounded), what came in
     * the month asked (this one by default, in the panel's time zone) and how it compares with the one before (null when nothing
     * came then), and how many plans renew in the next 30 days (a cancelling one ends instead) and what they are worth a year
     * before VAT.
     *
     * @return array{outstanding: string, overdue: string, open_statements: int, overdue_accounts: int, aging: list<array{key: string, amount: string, share: int}>, collected: string, month: string, previous_month: string, collected_change: int|null, renewing: int, renewing_yearly: string}
     */
    public function portfolio(?CarbonImmutable $month = null): array
    {
        $open = $this->open->handle();
        $byBucket = array_fill_keys(OpenBalances::BUCKETS, 0);
        $late = [];

        foreach ($open as $statement) {
            $byBucket[OpenBalances::bucket($statement['days'])] += $statement['balance'];

            if ($statement['days'] > 0) {
                $late[$statement['account_id']] = true;
            }
        }

        $total = array_sum($byBucket);
        $month ??= CarbonImmutable::now(config()->string('app.display_timezone'));
        $collected = $this->collected($month);
        $before = $this->collected($month->startOfMonth()->subMonth());

        return [
            'outstanding' => Money::format($total),
            'overdue' => Money::format($total - $byBucket['current']),
            'open_statements' => count($open),
            'overdue_accounts' => count($late),
            'aging' => array_map(fn (string $key): array => ['key' => $key, 'amount' => Money::format($byBucket[$key]), 'share' => $total === 0 ? 0 : intdiv($byBucket[$key] * 200 + $total, $total * 2)], OpenBalances::BUCKETS),
            'collected' => Money::format($collected),
            'month' => $month->isoFormat('MMMM'),
            'previous_month' => $month->startOfMonth()->subMonth()->isoFormat('MMMM'),
            'collected_change' => $before === 0 ? null : (int) round(($collected - $before) * 100 / $before),
            'renewing' => $this->receivables->renewingWithin(30),
            'renewing_yearly' => Money::format($this->renewingYearly()),
        ];
    }

    /** What the standing payments brought in the month of that day. */
    private function collected(CarbonImmutable $day): int
    {
        return (int) Payment::query()->standing()->whereBetween('paid_on', [$day->startOfMonth()->toDateString(), $day->endOfMonth()->toDateString()])->sum('amount_cents');
    }

    /** The plans that renew in the next 30 days (as Receivables::renewingWithin counts them), each priced as it pays a month, times twelve. */
    private function renewingYearly(): int
    {
        $tenants = Subscription::query()->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue])
            ->where('renews_on', '<=', CarbonImmutable::now(config()->string('app.display_timezone'))->addDays(30)->toDateString())
            ->get(['tenant_id']);

        $tenants = array_values(array_map(fn (Subscription $subscription): string => $subscription->tenant_id, $tenants->all()));

        return 12 * array_sum(array_map(fn (CompanyRevenue $company): int => $company->monthlyCents ?? 0, $this->revenue->byCompany($tenants)));
    }
}
