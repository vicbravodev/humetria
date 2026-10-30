<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Enums\TenantView;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListTenants
{
    private const int PER_PAGE = 25;

    /**
     * The companies whose names or RFC match the search, in one view, newest first, a page at a time.
     *
     * @return LengthAwarePaginator<int, Tenant>
     */
    public function handle(string $search = '', TenantView $view = TenantView::All): LengthAwarePaginator
    {
        return $this->matching($search, $view)
            ->with(['ownerMembership.user', 'openInvitation'])
            ->latest()
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * How many companies match the search in each view, and how many trials end within the next seven days, in one
     * query.
     *
     * @return array{counts: array<string, int>, trialsEndingSoon: int}
     */
    public function summary(string $search): array
    {
        $query = Tenant::query()->toBase()->limit(1);

        foreach (TenantView::cases() as $view) {
            $query->selectSub($this->matching($search, $view)->toBase()->selectRaw('count(*)'), $view->value);
        }

        $query->selectSub(Tenant::query()->inView(TenantView::Trial)
            ->where('trial_started_at', '<=', now()->subDays(Tenant::TRIAL_DAYS)->addWeek())
            ->toBase()->selectRaw('count(*)'), 'ending_soon');

        $row = (array) $query->first();
        $count = fn (string $key): int => is_int($row[$key] ?? null) ? $row[$key] : 0;

        return [
            'counts' => array_combine(
                array_map(fn (TenantView $view): string => $view->value, TenantView::cases()),
                array_map(fn (TenantView $view): int => $count($view->value), TenantView::cases()),
            ),
            'trialsEndingSoon' => $count('ending_soon'),
        ];
    }

    /**
     * @return Builder<Tenant>
     */
    private function matching(string $search, TenantView $view): Builder
    {
        return Tenant::query()->matching(['trade_name', 'legal_name', 'rfc'], $search)->inView($view);
    }
}
