<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Actions\AddCharge;
use App\Modules\Billing\Actions\EndCharge;
use App\Modules\Billing\Actions\RemoveCharge;
use App\Modules\Billing\Exceptions\CompanyHasNoPlan;
use App\Modules\Billing\Exceptions\InvalidCharge;
use App\Modules\Billing\Http\Requests\EndChargeRequest;
use App\Modules\Billing\Http\Requests\RemoveChargeRequest;
use App\Modules\Billing\Http\Requests\StoreChargeRequest;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\SubscriptionCharge;
use App\Modules\Tenancy\Contracts\CompanyRegistry;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class ChargeConsoleController extends Controller
{
    public function store(StoreChargeRequest $request, string $company, CompanyRegistry $companies, AddCharge $addCharge): RedirectResponse
    {
        abort_if($companies->find($company) === null, 404);

        try {
            $addCharge->handle($company, $request->toData(), $request->user() ?? abort(401));
        } catch (CompanyHasNoPlan $exception) {
            return back()->withErrors(['kind' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cargo agregado.']);

        $link = AccountCompany::query()->current()->where('tenant_id', $company)->first();

        return $link === null ? back() : redirect()->route('billing.accounts.show', $link->billing_account_id);
    }

    public function end(EndChargeRequest $request, SubscriptionCharge $charge, EndCharge $endCharge): RedirectResponse
    {
        try {
            $endCharge->handle($charge, $request->endsOn(), $request->reason());
        } catch (InvalidCharge $exception) {
            return back()->withErrors([$exception->field => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cargo terminado.']);

        return $this->toAccount($charge->tenant_id);
    }

    public function destroy(RemoveChargeRequest $request, SubscriptionCharge $charge, RemoveCharge $removeCharge): RedirectResponse
    {
        try {
            $removeCharge->handle($charge, $request->reason());
        } catch (InvalidCharge $exception) {
            return back()->withErrors([$exception->field => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cargo quitado.']);

        return $this->toAccount($charge->tenant_id);
    }

    private function toAccount(string $tenantId): RedirectResponse
    {
        $link = AccountCompany::query()->current()->where('tenant_id', $tenantId)->first();

        return $link === null ? back() : redirect()->route('billing.accounts.show', $link->billing_account_id);
    }
}
