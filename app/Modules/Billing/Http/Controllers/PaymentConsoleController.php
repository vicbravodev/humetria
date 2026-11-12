<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Actions\ApplyPayment;
use App\Modules\Billing\Actions\ListOpenStatements;
use App\Modules\Billing\Actions\RecordPayment;
use App\Modules\Billing\Actions\ReversePayment;
use App\Modules\Billing\Exceptions\InvalidPayment;
use App\Modules\Billing\Http\Requests\ApplyPaymentRequest;
use App\Modules\Billing\Http\Requests\RecordPaymentRequest;
use App\Modules\Billing\Http\Requests\ReversePaymentRequest;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class PaymentConsoleController extends Controller
{
    /** The statements a payment of the account can be applied to. */
    public function open(BillingAccount $account, ListOpenStatements $open): JsonResponse
    {
        Gate::authorize('manage-billing');

        return response()->json(['data' => $open->handle($account)]);
    }

    public function store(RecordPaymentRequest $request, BillingAccount $account, RecordPayment $record): RedirectResponse
    {
        try {
            $record->handle($account, $request->toData(), $request->user() ?? abort(401));
        } catch (InvalidPayment $exception) {
            return back()->withErrors([$exception->field => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pago registrado.']);

        return redirect()->route('billing.accounts.show', $account);
    }

    /** Applies what a recorded payment left unapplied to statements with a balance. */
    public function apply(ApplyPaymentRequest $request, Payment $payment, ApplyPayment $apply): RedirectResponse
    {
        try {
            $apply->handle($payment, $request->allocations(), $request->user() ?? abort(401));
        } catch (InvalidPayment $exception) {
            return back()->withErrors([$exception->field => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Saldo aplicado.']);

        return redirect()->route('billing.accounts.show', $payment->billing_account_id);
    }

    public function reverse(ReversePaymentRequest $request, Payment $payment, ReversePayment $reverse): RedirectResponse
    {
        try {
            $reverse->handle($payment, $request->string('reason')->toString(), $request->user() ?? abort(401));
        } catch (InvalidPayment $exception) {
            return back()->withErrors([$exception->field => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pago revertido.']);

        return redirect()->route('billing.accounts.show', $payment->billing_account_id);
    }
}
