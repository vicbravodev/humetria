<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\AccountKind;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Support\Money;
use App\Modules\Billing\Support\OpenBalances;
use App\Modules\Billing\Support\ShortDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListAccounts
{
    public function __construct(private OpenBalances $open) {}

    private const int PER_PAGE = 25;

    /**
     * The accounts whose legal name, RFC or contact has what was typed, of one kind or all (or only those with something to
     * collect, or past due, or whose oldest open balance is of one age), newest first, a page at a time, each with the number of
     * companies it pays now.
     *
     * @param  string|null  $age  one of OpenBalances::BUCKETS.
     * @return LengthAwarePaginator<int, BillingAccount>
     */
    public function handle(string $search = '', ?AccountKind $kind = null, bool $overdue = false, bool $receivable = false, ?string $age = null): LengthAwarePaginator
    {
        return $this->matching($search)
            ->when($age !== null, fn (Builder $query) => $query->whereIn('id', $this->agedAccounts($age ?? 'current')))
            ->when($overdue, fn (Builder $query) => $query->whereIn('id', $this->overdueAccounts()))
            ->when($receivable, fn (Builder $query) => $query->whereIn('id', $this->receivableAccounts()))
            ->when($kind !== null, fn (Builder $query) => $query->where('kind', $kind))
            ->withCount(['companies as current_companies_count' => fn (Builder $query) => $query->whereNull('ends_on')])
            ->latest()
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * How many accounts match the search, of each kind and in all.
     *
     * @return array{all: int, direct: int, consultant: int, overdue: int, receivable: int}
     */
    public function counts(string $search): array
    {
        $byKind = $this->matching($search)->toBase()->selectRaw('kind, count(*) as total')->groupBy('kind')->pluck('total', 'kind');
        $count = fn (AccountKind $kind): int => is_numeric($byKind->get($kind->value)) ? (int) $byKind->get($kind->value) : 0;

        return [
            'all' => $count(AccountKind::Direct) + $count(AccountKind::Consultant),
            'direct' => $count(AccountKind::Direct),
            'consultant' => $count(AccountKind::Consultant),
            'overdue' => $this->matching($search)->whereIn('id', $this->overdueAccounts())->count(),
            'receivable' => $this->matching($search)->whereIn('id', $this->receivableAccounts())->count(),
        ];
    }

    /**
     * @return Builder<BillingAccount>
     */
    private function matching(string $search): Builder
    {
        return BillingAccount::query()->matching(['legal_name', 'rfc', 'contact_name'], $search);
    }

    /**
     * Where each of the accounts stands: what it owes in all and past due, written as pesos, and how old its oldest open statement
     * is (`days` past due, or left to pay while it is current); an account that owes nothing is left out.
     *
     * @param  list<string>  $accountIds
     * @return array<string, array{outstanding: string, outstanding_cents: int, overdue: string|null, aging: array{bucket: string, days: int}}>
     */
    public function standing(array $accountIds): array
    {
        $owed = [];

        foreach ($this->open->handle($accountIds) as $statement) {
            $mine = $owed[$statement['account_id']] ?? ['outstanding' => 0, 'overdue' => 0, 'days' => $statement['days']];
            $owed[$statement['account_id']] = [
                'outstanding' => $mine['outstanding'] + $statement['balance'],
                'overdue' => $mine['overdue'] + ($statement['days'] > 0 ? $statement['balance'] : 0),
                'days' => $mine['days'],
            ];
        }

        return array_map(fn (array $account): array => [
            'outstanding' => Money::format($account['outstanding']),
            'outstanding_cents' => $account['outstanding'],
            'overdue' => $account['overdue'] === 0 ? null : Money::format($account['overdue']),
            'aging' => ['bucket' => OpenBalances::bucket($account['days']), 'days' => abs($account['days'])],
        ], $owed);
    }

    /**
     * The day of the last payment that stands of each of the accounts, as the panel writes it; an account that never paid is left out.
     *
     * @param  list<string>  $accountIds
     * @return array<string, string>
     */
    public function lastPayments(array $accountIds): array
    {
        $last = [];

        foreach (Payment::query()->standing()->whereIn('billing_account_id', $accountIds)->groupBy('billing_account_id')->toBase()->selectRaw('billing_account_id, max(paid_on) as paid_on')->get() as $row) {
            if (is_string($row->billing_account_id) && is_string($row->paid_on)) {
                $last[$row->billing_account_id] = ShortDate::format(CarbonImmutable::parse($row->paid_on));
            }
        }

        return $last;
    }

    private function receivableAccounts(): QueryBuilder
    {
        return Statement::query()->withBalance()->toBase()->select('billing_account_id');
    }

    /**
     * The accounts whose oldest open statement is of that age, by the day it was issued: past due on day
     * `billing.overdue_after_days` (as OpenBalances counts it), then 1–30, 31–60 and more than 60 days.
     */
    private function agedAccounts(string $age): QueryBuilder
    {
        $now = CarbonImmutable::now();
        $due = config()->integer('billing.overdue_after_days');
        [$after, $until] = match ($age) {
            '1_30' => [$due + 30, $due],
            '31_60' => [$due + 60, $due + 30],
            'over_60' => [null, $due + 60],
            default => [$due, null],
        };

        return Statement::query()->withBalance()->toBase()->select('billing_account_id')->groupBy('billing_account_id')
            ->when($after !== null, fn (QueryBuilder $query) => $query->havingRaw('min(issued_at) > ?', [$now->subDays($after ?? 0)]))
            ->when($until !== null, fn (QueryBuilder $query) => $query->havingRaw('min(issued_at) <= ?', [$now->subDays($until ?? 0)]));
    }

    private function overdueAccounts(): QueryBuilder
    {
        return Statement::query()->overdue(CarbonImmutable::now())->toBase()->select('billing_account_id');
    }
}
