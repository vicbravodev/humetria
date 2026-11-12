<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\AccountKind;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Support\Money;
use App\Modules\Billing\Support\StatementBalances;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListAccounts
{
    public function __construct(private StatementBalances $balances) {}

    private const int PER_PAGE = 25;

    /**
     * The accounts whose legal name, RFC or contact has what was typed, of one kind or all, newest first, a page at a time,
     * each with the number of companies it pays now.
     *
     * @return LengthAwarePaginator<int, BillingAccount>
     */
    public function handle(string $search = '', ?AccountKind $kind = null, bool $overdue = false): LengthAwarePaginator
    {
        return $this->matching($search)
            ->when($overdue, fn (Builder $query) => $query->whereIn('id', $this->overdueAccounts()))
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
     * @return array{all: int, direct: int, consultant: int, overdue: int}
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
     * What each of the accounts owes past due, written as pesos; an account with nothing past due is left out.
     *
     * @param  list<string>  $accountIds
     * @return array<string, string>
     */
    public function overdueAmounts(array $accountIds): array
    {
        $statements = Statement::query()->overdue(CarbonImmutable::now())->whereIn('billing_account_id', $accountIds)->get(['id', 'billing_account_id', 'total_cents']);
        $paid = $this->balances->paid(array_values($statements->map(fn (Statement $statement): string => $statement->id)->all()));
        $owed = [];

        foreach ($statements as $statement) {
            $owed[$statement->billing_account_id] = ($owed[$statement->billing_account_id] ?? 0) + $statement->total_cents - ($paid[$statement->id] ?? 0);
        }

        return array_map(Money::format(...), $owed);
    }

    private function overdueAccounts(): QueryBuilder
    {
        return Statement::query()->overdue(CarbonImmutable::now())->toBase()->select('billing_account_id');
    }
}
