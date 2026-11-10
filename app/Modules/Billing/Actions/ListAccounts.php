<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\AccountKind;
use App\Modules\Billing\Models\BillingAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListAccounts
{
    private const int PER_PAGE = 25;

    /**
     * The accounts whose legal name, RFC or contact has what was typed, of one kind or all, newest first, a page at a time,
     * each with the number of companies it pays now.
     *
     * @return LengthAwarePaginator<int, BillingAccount>
     */
    public function handle(string $search = '', ?AccountKind $kind = null): LengthAwarePaginator
    {
        return $this->matching($search)
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
     * @return array{all: int, direct: int, consultant: int}
     */
    public function counts(string $search): array
    {
        $byKind = $this->matching($search)->toBase()->selectRaw('kind, count(*) as total')->groupBy('kind')->pluck('total', 'kind');
        $count = fn (AccountKind $kind): int => is_numeric($byKind->get($kind->value)) ? (int) $byKind->get($kind->value) : 0;

        return [
            'all' => $count(AccountKind::Direct) + $count(AccountKind::Consultant),
            'direct' => $count(AccountKind::Direct),
            'consultant' => $count(AccountKind::Consultant),
        ];
    }

    /**
     * @return Builder<BillingAccount>
     */
    private function matching(string $search): Builder
    {
        return BillingAccount::query()->matching(['legal_name', 'rfc', 'contact_name'], $search);
    }
}
