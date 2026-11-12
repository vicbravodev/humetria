<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Data\TransferRow;
use App\Modules\Billing\Enums\Profile;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\CompanyTransfer;
use App\Modules\Billing\Support\ShortDate;
use App\Modules\Tenancy\Contracts\CompanyRegistry;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListAccountTransfers
{
    private const int PER_PAGE = 25;

    public function __construct(private CompanyRegistry $companies) {}

    /**
     * The companies that left the account or came to it, the latest cut first, a page at a time.
     *
     * @return LengthAwarePaginator<int, TransferRow>
     */
    public function handle(BillingAccount $account): LengthAwarePaginator
    {
        $page = CompanyTransfer::query()
            ->where(fn ($query) => $query->where('from_account_id', $account->id)->orWhere('to_account_id', $account->id))
            ->orderByDesc('effective_on')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE, pageName: 'transfers_page')
            ->withQueryString();

        $items = $page->items();
        $briefs = $this->companies->many(array_values(array_unique(array_map(fn (CompanyTransfer $transfer): string => $transfer->tenant_id, $items))));
        $accountIds = array_values(array_unique(array_filter(array_merge(...array_map(fn (CompanyTransfer $transfer): array => [$transfer->from_account_id, $transfer->to_account_id], $items)))));
        $accounts = [];
        $people = [];

        foreach (BillingAccount::query()->whereIn('id', $accountIds)->get(['id', 'legal_name']) as $payer) {
            $accounts[$payer->id] = $payer->legal_name;
        }

        foreach (User::query()->whereIn('id', array_map(fn (CompanyTransfer $transfer): int => $transfer->created_by, $items))->get(['id', 'name']) as $user) {
            $people[$user->id] = $user->name;
        }

        return $page->through(function (CompanyTransfer $transfer) use ($account, $briefs, $accounts, $people): TransferRow {
            $leaving = $transfer->from_account_id === $account->id;
            $otherId = $leaving ? $transfer->to_account_id : $transfer->from_account_id;

            return new TransferRow(
                $transfer->id,
                $briefs[$transfer->tenant_id]->tradeName,
                $leaving ? 'out' : 'in',
                $otherId === null ? 'Sin pagador' : $accounts[$otherId] ?? '',
                ShortDate::format($transfer->effective_on),
                $transfer->reason,
                $people[$transfer->created_by] ?? '',
                $transfer->profile_before === null || $transfer->profile_after === null ? null : Profile::from($transfer->profile_before)->label().' a '.Profile::from($transfer->profile_after)->label(),
            );
        });
    }
}
