<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Data\AccountCompanyRow;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Support\ShortDate;
use App\Modules\Tenancy\Contracts\CompanyRegistry;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListAccountCompanies
{
    private const int PER_PAGE = 25;

    public function __construct(private CompanyRegistry $companies) {}

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

        $briefs = $this->companies->many(array_values(array_map(fn (AccountCompany $link): string => $link->tenant_id, $page->items())));

        return $page->through(function (AccountCompany $link) use ($briefs): AccountCompanyRow {
            $brief = $briefs[$link->tenant_id];

            return new AccountCompanyRow(
                $brief->id,
                $brief->tradeName,
                $brief->legalName,
                $brief->rfc,
                $brief->slug,
                $brief->status,
                $brief->statusLabel,
                $brief->workCenters,
                ShortDate::format($link->starts_on),
                null,
            );
        });
    }
}
