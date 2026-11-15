<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Actions\LinkCompany;
use App\Modules\Billing\Exceptions\CompanyAlreadyHasPayer;
use App\Modules\Billing\Http\Requests\LinkCompanyRequest;
use App\Modules\Billing\Models\BillingAccount;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class AccountCompaniesController extends Controller
{
    public function store(LinkCompanyRequest $request, BillingAccount $account, LinkCompany $linkCompany): RedirectResponse
    {
        try {
            $linkCompany->handle($account, $request->companyId(), $request->startsOn());
        } catch (CompanyAlreadyHasPayer $exception) {
            return back()->withErrors(['company_id' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Empresa vinculada.']);

        return redirect()->route('billing.accounts.show', $account);
    }
}
