<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Data\AccountCompanyRow;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\CompanyTransfer;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\Eligibility;
use App\Modules\Billing\Support\ShortDate;
use App\Modules\Billing\Support\SubscriptionQuoter;
use App\Modules\Billing\Support\SubscriptionView;
use App\Modules\Organization\Contracts\Headcount;
use App\Modules\Tenancy\Contracts\CompanyRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListAccountCompanies
{
    private const int PER_PAGE = 25;

    public function __construct(
        private CompanyRegistry $companies,
        private Headcount $headcount,
        private Eligibility $eligibility,
        private SubscriptionQuoter $quoter,
    ) {}

    /**
     * The companies the account pays now, newest link first, a page at a time. Each name, status and size is read through
     * Tenancy's contract, once for the whole page.
     *
     * @return LengthAwarePaginator<int, AccountCompanyRow>
     */
    public function handle(BillingAccount $account): LengthAwarePaginator
    {
        $page = AccountCompany::query()->current()
            ->where('billing_account_id', $account->id)
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE, pageName: 'companies_page')
            ->withQueryString();

        $ids = array_values(array_map(fn (AccountCompany $link): string => $link->tenant_id, $page->items()));
        $briefs = $this->companies->many($ids);
        $subscriptions = Subscription::query()->whereIn('tenant_id', $ids)->get()->keyBy('tenant_id');
        $eligible = $this->eligibility->count($account);
        $arrivals = $this->arrivals($account, $ids);
        $today = CarbonImmutable::now(config()->string('app.display_timezone'))->startOfDay();

        return $page->through(function (AccountCompany $link) use ($briefs, $subscriptions, $eligible, $today, $arrivals): AccountCompanyRow {
            $brief = $briefs[$link->tenant_id];
            $subscription = $subscriptions->get($link->tenant_id);
            $people = $this->headcount->ofCompany($link->tenant_id);

            return new AccountCompanyRow(
                $brief->id,
                $brief->tradeName,
                $brief->legalName,
                $brief->rfc,
                $brief->slug,
                $brief->status,
                $brief->statusLabel,
                $brief->workCenters,
                $people,
                ShortDate::format($link->starts_on),
                $subscription === null ? null : SubscriptionView::from($subscription, $this->quoter->quote($subscription, $eligible, $today)),
                $arrivals[$link->tenant_id] ?? null,
            );
        });
    }

    /**
     * Who paid each of these companies before, for those that came to the account by a transfer: the account they left.
     *
     * @param  list<string>  $ids
     * @return array<string, string> account name, by company id.
     */
    private function arrivals(BillingAccount $account, array $ids): array
    {
        $transfers = CompanyTransfer::query()->where('to_account_id', $account->id)->whereIn('tenant_id', $ids)->orderBy('effective_on')->orderBy('created_at')->get(['tenant_id', 'from_account_id']);
        $names = [];

        foreach (BillingAccount::query()->whereIn('id', $transfers->pluck('from_account_id'))->get(['id', 'legal_name']) as $payer) {
            $names[$payer->id] = $payer->legal_name;
        }

        $arrivals = [];

        foreach ($transfers as $transfer) {
            $arrivals[$transfer->tenant_id] = $names[$transfer->from_account_id] ?? '';
        }

        return $arrivals;
    }
}
