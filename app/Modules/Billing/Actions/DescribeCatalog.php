<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\Catalog;
use App\Modules\Billing\Support\Money;
use App\Modules\Billing\Support\ShortDate;
use App\Modules\Tenancy\Contracts\CompanyRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The catalog in force as the console reads it: it lives in code (config/billing.php) with its version, so the page only shows
 * it. Each plan with its price, centers and modules, how many companies are on it and how many of them renew in 90 days; and
 * the prices agreed by hand that are in force.
 */
final readonly class DescribeCatalog
{
    private const int PER_PAGE = 25; // @pest-mutate-ignore: DecrementInteger,IncrementInteger

    /** A search reads at most this many companies by name. ponytail: raise it (or search in Tenancy by id) if it ever falls short. */
    private const int SEARCH_LIMIT = 200; // @pest-mutate-ignore: DecrementInteger,IncrementInteger

    public function __construct(private CompanyRegistry $companies) {}

    /**
     * @return array{version: string, effective_on: string, vat_percent: int, modules: list<string>, plans: list<array{key: string, label: string, rate: string|null, flat: string|null, minimum: string|null, included_centers: int, modules: list<string>, companies: int, renewing: int}>}
     */
    public function handle(): array
    {
        $catalog = Catalog::current();
        $billed = [SubscriptionStatus::Active, SubscriptionStatus::PastDue, SubscriptionStatus::CancelScheduled];
        $companies = $this->countByPlan(Subscription::query()->whereIn('status', $billed));
        $renewing = $this->countByPlan(Subscription::query()->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue])
            ->where('renews_on', '<=', CarbonImmutable::now(config()->string('app.display_timezone'))->addDays(90)->toDateString()));
        $plans = [];

        foreach (Plan::cases() as $plan) {
            // A union's plan is one flat fee; every other one is a rate per person with a minimum.
            $flat = $plan === Plan::Sindicato;
            $plans[] = [
                'key' => $plan->value,
                'label' => $plan->label(),
                'rate' => $flat ? null : Money::format($catalog->rateCents($plan)),
                'flat' => $flat ? Money::format($catalog->flatCents($plan)) : null,
                'minimum' => $flat ? null : Money::format($catalog->minimumCents($plan)),
                'included_centers' => $catalog->includedCenters($plan),
                'modules' => $catalog->modules($plan),
                'companies' => $companies[$plan->value] ?? 0,
                'renewing' => $renewing[$plan->value] ?? 0,
            ];
        }

        return [
            'version' => $catalog->name(),
            'effective_on' => ShortDate::format(CarbonImmutable::parse($catalog->effectiveOn())),
            'vat_percent' => $catalog->vatPercent(),
            'modules' => $catalog->allModules(),
            'plans' => $plans,
        ];
    }

    /**
     * The prices agreed by hand still in force, of plans not ended, the one that ends first on top (one with no end last), by the
     * company's name when something was typed, a page at a time.
     *
     * @return LengthAwarePaginator<int, array{company: string, plan: string, price: string, valid_until: string|null}>
     */
    public function negotiated(string $search): LengthAwarePaginator
    {
        $today = CarbonImmutable::now(config()->string('app.display_timezone'))->toDateString();
        $page = Subscription::query()
            ->whereNotNull('negotiated_price_cents')
            ->where('status', '<>', SubscriptionStatus::Ended)
            ->where(fn (Builder $query) => $query->whereNull('negotiated_valid_until')->orWhere('negotiated_valid_until', '>=', $today))
            ->when($search !== '', fn (Builder $query) => $query->whereIn('tenant_id', array_map(fn ($brief): string => $brief->id, $this->companies->search($search, self::SEARCH_LIMIT))))
            ->orderByRaw('negotiated_valid_until asc nulls last')->orderBy('id')
            ->paginate(self::PER_PAGE)->withQueryString();
        $names = $this->companies->many(array_values($page->getCollection()->map(fn (Subscription $subscription): string => $subscription->tenant_id)->all()));

        return $page->through(fn (Subscription $subscription): array => [
            'company' => $names[$subscription->tenant_id]->tradeName ?? '',
            'plan' => $subscription->plan->label(),
            'price' => Money::format($subscription->negotiated_price_cents ?? 0),
            'valid_until' => $subscription->negotiated_valid_until === null ? null : ShortDate::format($subscription->negotiated_valid_until),
        ]);
    }

    /**
     * @param  Builder<Subscription>  $query
     * @return array<string, int>
     */
    private function countByPlan(Builder $query): array
    {
        $counts = [];

        foreach ($query->toBase()->selectRaw('plan, count(*) as total')->groupBy('plan')->get() as $row) {
            if (is_string($row->plan) && is_numeric($row->total)) {
                $counts[$row->plan] = (int) $row->total;
            }
        }

        return $counts;
    }
}
