<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Actions\SetSubscription;
use App\Modules\Billing\Exceptions\InvalidSubscription;
use App\Modules\Billing\Http\Requests\UpdateSubscriptionRequest;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Tenancy\Contracts\CompanyRegistry;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class SubscriptionConsoleController extends Controller
{
    public function update(UpdateSubscriptionRequest $request, string $company, CompanyRegistry $companies, SetSubscription $setSubscription): RedirectResponse
    {
        abort_if($companies->find($company) === null, 404);

        $existed = Subscription::query()->where('tenant_id', $company)->exists();

        try {
            $setSubscription->handle($company, $request->toData());
        } catch (InvalidSubscription $exception) {
            return back()->withErrors([$exception->field => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $existed ? 'Plan actualizado.' : 'Plan asignado.']);

        $link = AccountCompany::query()->current()->where('tenant_id', $company)->firstOrFail();

        return redirect()->route('billing.accounts.show', $link->billing_account_id);
    }
}
