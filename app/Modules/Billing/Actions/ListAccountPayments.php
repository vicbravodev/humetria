<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Data\PaymentRow;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\PaymentAllocation;
use App\Modules\Billing\Support\Money;
use App\Modules\Billing\Support\ShortDate;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListAccountPayments
{
    private const int PER_PAGE = 25;

    /**
     * The payments of the account, the latest first, a page at a time, with who recorded each, how much of it was applied and to
     * which months, and the reversed ones marked with their reason.
     *
     * @return LengthAwarePaginator<int, PaymentRow>
     */
    public function handle(BillingAccount $account): LengthAwarePaginator
    {
        $page = Payment::query()
            ->where('billing_account_id', $account->id)
            ->orderByDesc('paid_on')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE, pageName: 'payments_page')
            ->withQueryString();

        $items = $page->items();
        $applied = [];
        $months = [];
        $allocations = PaymentAllocation::query()
            ->with('statement:id,period_start')
            ->whereIn('payment_id', array_map(fn (Payment $payment): string => $payment->id, $items))
            ->get(['payment_id', 'statement_id', 'amount_cents'])
            ->sortBy(fn (PaymentAllocation $allocation): string => $allocation->statement?->period_start->toDateString() ?? '');

        foreach ($allocations as $allocation) {
            $applied[$allocation->payment_id] = ($applied[$allocation->payment_id] ?? 0) + $allocation->amount_cents;
            $period = $allocation->statement?->period_start;

            if ($period !== null) {
                $months[$allocation->payment_id] = array_values(array_unique([...$months[$allocation->payment_id] ?? [], ShortDate::month($period)]));
            }
        }

        $names = [];

        foreach (User::query()->whereIn('id', array_map(fn (Payment $payment): int => $payment->recorded_by, $items))->get(['id', 'name']) as $user) {
            $names[$user->id] = $user->name;
        }

        return $page->through(fn (Payment $payment): PaymentRow => new PaymentRow(
            $payment->id,
            $payment->reference,
            ShortDate::format($payment->paid_on),
            Money::format($payment->amount_cents),
            Money::format($applied[$payment->id] ?? 0),
            Money::format($payment->amount_cents - ($applied[$payment->id] ?? 0)),
            $payment->note,
            $names[$payment->recorded_by] ?? '',
            $payment->isReversed(),
            $payment->reversal_reason,
            ! $payment->isReversed() && $payment->amount_cents > ($applied[$payment->id] ?? 0),
            $months[$payment->id] ?? [],
        ));
    }
}
