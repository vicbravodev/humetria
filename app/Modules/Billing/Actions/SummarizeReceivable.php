<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\Money;
use App\Modules\Billing\Support\OpenBalances;
use App\Modules\Billing\Support\StatementBalances;
use Carbon\CarbonImmutable;

final readonly class SummarizeReceivable
{
    public function __construct(private StatementBalances $balances, private OpenBalances $open) {}

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
            'outstanding' => Money::format($this->balances->outstanding($account)),
            'open_statements' => count($open),
            'overdue' => $overdue === 0 ? null : Money::format($overdue),
            'aging' => $oldest === null ? null : ['bucket' => OpenBalances::bucket($oldest), 'days' => abs($oldest)],
        ];
    }

    /**
     * What all the accounts owe, split by how long it is past due (each part with its share of the whole, rounded), what came in
     * this month in the panel's time zone, and how many plans renew in the next 30 days (a cancelling one ends instead).
     *
     * @return array{outstanding: string, overdue: string, open_statements: int, overdue_accounts: int, aging: list<array{key: string, amount: string, share: int}>, collected: string, month: string, renewing: int}
     */
    public function portfolio(): array
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
        $today = CarbonImmutable::now(config()->string('app.display_timezone'));

        return [
            'outstanding' => Money::format($total),
            'overdue' => Money::format($total - $byBucket['current']),
            'open_statements' => count($open),
            'overdue_accounts' => count($late),
            'aging' => array_map(fn (string $key): array => ['key' => $key, 'amount' => Money::format($byBucket[$key]), 'share' => $total === 0 ? 0 : (int) round($byBucket[$key] * 100 / $total)], OpenBalances::BUCKETS),
            'collected' => Money::format((int) Payment::query()->standing()->whereBetween('paid_on', [$today->startOfMonth()->toDateString(), $today->endOfMonth()->toDateString()])->sum('amount_cents')),
            'month' => $today->isoFormat('MMMM'),
            'renewing' => Subscription::query()->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue])->where('renews_on', '<=', $today->addDays(30)->toDateString())->count(),
        ];
    }
}
