<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Data\AccountChargeRow;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\SubscriptionCharge;
use App\Modules\Billing\Support\Money;
use App\Modules\Billing\Support\ShortDate;
use App\Modules\Tenancy\Contracts\CompanyRegistry;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListAccountCharges
{
    private const int PER_PAGE = 25;

    public function __construct(private CompanyRegistry $companies) {}

    /**
     * The extra charges of the companies the account pays now, newest first, a page at a time, with who approved each.
     *
     * @return LengthAwarePaginator<int, AccountChargeRow>
     */
    public function handle(BillingAccount $account): LengthAwarePaginator
    {
        $page = SubscriptionCharge::query()
            ->whereIn('tenant_id', AccountCompany::query()->current()->where('billing_account_id', $account->id)->select('tenant_id'))
            ->orderByDesc('charge_on')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE, pageName: 'charges_page')
            ->withQueryString();

        $briefs = $this->companies->many(array_values(array_unique(array_map(fn (SubscriptionCharge $charge): string => $charge->tenant_id, $page->items()))));

        $approvers = [];

        foreach (User::query()->whereIn('id', array_map(fn (SubscriptionCharge $charge): int => $charge->approved_by, $page->items()))->get(['id', 'name']) as $user) {
            $approvers[$user->id] = $user->name;
        }

        return $page->through(fn (SubscriptionCharge $charge): AccountChargeRow => new AccountChargeRow(
            $charge->id,
            $charge->tenant_id,
            $briefs[$charge->tenant_id]->tradeName,
            $charge->kind->value,
            $charge->kind->label(),
            $charge->description,
            $charge->scope,
            Money::format($charge->amount_cents),
            ShortDate::format($charge->charge_on),
            $charge->ends_on === null ? null : ShortDate::format($charge->ends_on),
            $approvers[$charge->approved_by] ?? '',
        ));
    }
}
