<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Contracts\CompanyLimits;
use App\Modules\Tenancy\Data\TenantNarrowing;
use App\Modules\Tenancy\Enums\TenantView;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class ListTenants
{
    private const int PER_PAGE = 25;

    /**
     * The companies whose names or RFC match the search, in one view, newest first, a page at a time.
     *
     * @return LengthAwarePaginator<int, Tenant>
     */
    public function handle(string $search = '', TenantView $view = TenantView::All, ?TenantNarrowing $narrowing = null): LengthAwarePaginator
    {
        return $this->matching($search, $view, $narrowing)
            ->select('tenants.*')
            // The latest sign-in of anyone who belongs to the company, in the same query as the page.
            ->selectSub(DB::table('tenant_memberships')
                ->join('users', 'users.id', '=', 'tenant_memberships.user_id')
                ->whereColumn('tenant_memberships.tenant_id', 'tenants.id')
                ->selectRaw('max(users.last_login_at)'), 'last_access_at')
            // The seats it uses of its plan and who at Humetría answers for it, in the same query too.
            ->selectSub($this->seats('used'), 'seats_used')
            ->selectSub($this->seats('amount'), 'seats_amount')
            ->selectSub(DB::table('users')->whereColumn('users.id', 'tenants.owner_user_id')->select('name'), 'account_manager_name')
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
    public function summary(string $search, ?TenantNarrowing $narrowing = null): array
    {
        $query = Tenant::query()->toBase()->limit(1);

        foreach (TenantView::cases() as $view) {
            $query->selectSub($this->matching($search, $view, $narrowing)->toBase()->selectRaw('count(*)'), $view->value);
        }

        $query->selectSub($this->matching('', TenantView::TrialEnding)->toBase()->selectRaw('count(*)'), 'ending_soon');

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

    private function seats(string $column): QueryBuilder
    {
        return DB::table('tenant_limits')->whereColumn('tenant_limits.tenant_id', 'tenants.id')
            ->where('resource', CompanyLimits::SEATS)->select($column);
    }

    /**
     * The demo company is not a customer: it lives in its own room of the console, never in the list nor its counts.
     *
     * @return Builder<Tenant>
     */
    private function matching(string $search, TenantView $view, ?TenantNarrowing $narrowing = null): Builder
    {
        $query = Tenant::query()->where('is_demo', false)->matching(['trade_name', 'legal_name', 'rfc'], $search)->inView($view);

        return $narrowing === null ? $query : $this->narrow($query, $narrowing);
    }

    /**
     * @param  Builder<Tenant>  $query
     * @return Builder<Tenant>
     */
    private function narrow(Builder $query, TenantNarrowing $narrowing): Builder
    {
        return $query
            ->when($narrowing->manager === TenantNarrowing::NOBODY, fn (Builder $query) => $query->whereNull('owner_user_id'))
            ->when($narrowing->manager !== null && $narrowing->manager !== TenantNarrowing::NOBODY, fn (Builder $query) => $query->where('owner_user_id', $narrowing->manager))
            // «Uso > 80 %»: the seats it uses reach four fifths of what its plan covers.
            ->when($narrowing->highUsage, fn (Builder $query) => $query->whereExists(fn (QueryBuilder $limits) => $limits->from('tenant_limits')
                ->whereColumn('tenant_limits.tenant_id', 'tenants.id')
                ->where('resource', CompanyLimits::SEATS)
                ->where('amount', '>', 0)
                ->whereRaw('used * 5 >= amount * 4')))
            ->when($narrowing->companies !== null, fn (Builder $query) => $query->whereIn('tenants.id', $narrowing->companies ?? []));
    }
}
