<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Contracts\Receivables;
use App\Modules\Billing\Data\CompanyPayer;
use App\Modules\Billing\Data\OverdueAccount;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Subscription;
use Carbon\CarbonImmutable;

/** What is left to collect, read from the open statements (OpenBalances) and the subscriptions. */
final readonly class LedgerReceivables implements Receivables
{
    public function __construct(private OpenBalances $open) {}

    public function overdueCents(): int
    {
        return array_sum(array_map(fn (array $statement): int => $statement['days'] > 0 ? $statement['balance'] : 0, $this->open->handle()));
    }

    public function overdueAccounts(int $limit = 5): array
    {
        $late = [];

        foreach ($this->open->handle() as $statement) {
            if ($statement['days'] > 0) {
                $mine = $late[$statement['account_id']] ?? ['cents' => 0, 'days' => 0];
                $late[$statement['account_id']] = ['cents' => $mine['cents'] + $statement['balance'], 'days' => max($mine['days'], $statement['days'])];
            }
        }

        uasort($late, fn (array $a, array $b): int => $b['days'] <=> $a['days']);
        $late = array_slice($late, 0, $limit, true);
        $found = BillingAccount::query()->whereIn('id', array_keys($late))->get(['id', 'legal_name'])->keyBy('id');
        $accounts = [];

        foreach ($late as $id => $owed) {
            $account = $found->get($id);

            if ($account !== null) {
                $accounts[] = new OverdueAccount($account->id, $account->legal_name, $owed['cents'], $owed['days']);
            }
        }

        return $accounts;
    }

    public function renewingWithin(int $days): int
    {
        return Subscription::query()->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue])
            ->where('renews_on', '<=', CarbonImmutable::now(config()->string('app.display_timezone'))->addDays($days)->toDateString())
            ->count();
    }

    public function payerOf(string $tenantId): ?CompanyPayer
    {
        $account = AccountCompany::query()->current()->where('tenant_id', $tenantId)->with('account')->first()?->account;

        if ($account === null) {
            return null;
        }

        $late = array_filter($this->open->handle([$account->id]), fn (array $statement): bool => $statement['days'] > 0);

        return new CompanyPayer(
            $account->id,
            $account->legal_name,
            $account->kind->label(),
            array_sum(array_column($late, 'balance')),
            max([0, ...array_column($late, 'days')]),
        );
    }
}
