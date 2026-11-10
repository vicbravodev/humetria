<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Actions\CreateAccount;
use App\Modules\Billing\Actions\ListAccountCompanies;
use App\Modules\Billing\Actions\ListAccounts;
use App\Modules\Billing\Actions\SummarizeAccount;
use App\Modules\Billing\Actions\UpdateAccount;
use App\Modules\Billing\Enums\AccountKind;
use App\Modules\Billing\Http\Requests\StoreAccountRequest;
use App\Modules\Billing\Http\Requests\UpdateAccountRequest;
use App\Modules\Billing\Models\BillingAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class AccountConsoleController extends Controller
{
    public function index(Request $request, ListAccounts $listAccounts): Response
    {
        Gate::authorize('view-billing');

        $search = trim($request->string('q')->toString());
        $kind = AccountKind::tryFrom($request->string('kind')->toString());

        return Inertia::render('billing/accounts', [
            'filters' => ['q' => $search, 'kind' => $kind->value ?? 'all'],
            'counts' => $listAccounts->counts($search),
            'accounts' => $listAccounts->handle($search, $kind)->through(fn (BillingAccount $account): array => [
                ...$this->summary($account),
                'companies' => $account->getAttribute('current_companies_count'),
            ]),
        ]);
    }

    public function store(StoreAccountRequest $request, CreateAccount $createAccount): RedirectResponse
    {
        $account = $createAccount->handle($request->toData($request->kind()));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cuenta creada.']);

        return redirect()->route('billing.accounts.show', $account);
    }

    public function show(BillingAccount $account, ListAccountCompanies $listCompanies, SummarizeAccount $summarize): Response
    {
        Gate::authorize('view-billing');

        return Inertia::render('billing/account', [
            'account' => $this->detail($account),
            'summary' => $summarize->handle($account),
            'companies' => $listCompanies->handle($account),
        ]);
    }

    public function update(UpdateAccountRequest $request, BillingAccount $account, UpdateAccount $updateAccount): RedirectResponse
    {
        $updateAccount->handle($account, $request->toData($account->kind));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cuenta actualizada.']);

        return redirect()->route('billing.accounts.show', $account);
    }

    /**
     * @return array{id: string, kind: string, kind_label: string, legal_name: string, rfc: string, contact_name: string, contact_email: string}
     */
    private function summary(BillingAccount $account): array
    {
        return [
            'id' => $account->id,
            'kind' => $account->kind->value,
            'kind_label' => $account->kind->label(),
            'legal_name' => $account->legal_name,
            'rfc' => $account->rfc,
            'contact_name' => $account->contact_name,
            'contact_email' => $account->contact_email,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function detail(BillingAccount $account): array
    {
        return [
            ...$this->summary($account),
            'tax_regime' => $account->tax_regime,
            'postal_code' => $account->postal_code,
            'cfdi_use' => $account->cfdi_use,
            'contact_phone' => $account->contact_phone,
            'notes' => $account->notes,
        ];
    }
}
