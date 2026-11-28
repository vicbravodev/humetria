<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Actions\CreateAccount;
use App\Modules\Billing\Actions\ListAccountCharges;
use App\Modules\Billing\Actions\ListAccountCompanies;
use App\Modules\Billing\Actions\ListAccountPayments;
use App\Modules\Billing\Actions\ListAccounts;
use App\Modules\Billing\Actions\ListAccountStatements;
use App\Modules\Billing\Actions\ListAccountTransfers;
use App\Modules\Billing\Actions\SummarizeAccount;
use App\Modules\Billing\Actions\SummarizeAccountYear;
use App\Modules\Billing\Actions\SummarizeReceivable;
use App\Modules\Billing\Actions\UpdateAccount;
use App\Modules\Billing\Enums\AccountKind;
use App\Modules\Billing\Http\Requests\StoreAccountRequest;
use App\Modules\Billing\Http\Requests\UpdateAccountRequest;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Support\OpenBalances;
use App\Modules\Billing\Support\SatCatalogs;
use App\Modules\Billing\Support\ShortDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class AccountConsoleController extends Controller
{
    public function index(Request $request, ListAccounts $listAccounts, SummarizeReceivable $receivable): Response
    {
        Gate::authorize('view-billing');

        $search = trim($request->string('q')->toString());
        $view = $request->string('kind')->toString();
        $kind = AccountKind::tryFrom($view);
        $view = $kind !== null || in_array($view, ['overdue', 'receivable'], true) ? $view : 'all';
        $type = AccountKind::tryFrom($request->string('type')->toString());
        $age = in_array($request->string('age')->toString(), OpenBalances::BUCKETS, true) ? $request->string('age')->toString() : null;
        $month = $this->month($request->string('month')->toString());
        $accounts = $listAccounts->handle($search, $kind ?? $type, $view === 'overdue', $view === 'receivable', $age);
        $ids = array_values($accounts->getCollection()->map(fn (BillingAccount $account): string => $account->id)->all());
        $standing = $listAccounts->standing($ids);
        $paid = $listAccounts->lastPayments($ids);

        return Inertia::render('billing/accounts', [
            ...$this->satCatalogs(),
            'filters' => ['q' => $search, 'kind' => $view, 'type' => $type->value ?? '', 'age' => $age ?? '', 'month' => $month?->format('Y-m') ?? ''],
            'counts' => $listAccounts->counts($search),
            'receivable' => $receivable->portfolio($month),
            'months' => $this->months(),
            'accounts' => $accounts->through(fn (BillingAccount $account): array => [
                ...$this->summary($account),
                'companies' => $account->getAttribute('current_companies_count'),
                'outstanding' => $standing[$account->id]['outstanding'] ?? null,
                'outstanding_cents' => $standing[$account->id]['outstanding_cents'] ?? 0,
                'overdue' => $standing[$account->id]['overdue'] ?? null,
                'aging' => $standing[$account->id]['aging'] ?? null,
                'last_payment' => $paid[$account->id] ?? null,
            ]),
        ]);
    }

    public function store(StoreAccountRequest $request, CreateAccount $createAccount): RedirectResponse
    {
        $account = $createAccount->handle($request->toData($request->kind()));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cuenta creada.']);

        return redirect()->route('billing.accounts.show', $account);
    }

    public function show(Request $request, BillingAccount $account, ListAccountCompanies $listCompanies, ListAccountCharges $listCharges, SummarizeAccount $summarize, ListAccountStatements $listStatements, ListAccountPayments $listPayments, SummarizeReceivable $receivable, ListAccountTransfers $listTransfers, SummarizeAccountYear $year): Response
    {
        Gate::authorize('view-billing');

        $open = $request->string('statements')->toString() === 'open';

        return Inertia::render('billing/account', [
            ...$this->satCatalogs(),
            'account' => $this->detail($account),
            // The day a new plan starts unless another is chosen: today, in the time zone the panel shows.
            'today' => now(config()->string('app.display_timezone'))->toDateString(),
            'summary' => $summarize->handle($account),
            'companies' => $listCompanies->handle($account),
            'charges' => $listCharges->handle($account),
            'statements' => $listStatements->handle($account, $open),
            'statementsView' => $open ? 'open' : 'all',
            'year' => $year->handle($account),
            'payments' => $listPayments->handle($account),
            'receivable' => $receivable->handle($account),
            'transfers' => $listTransfers->handle($account),
        ]);
    }

    public function update(UpdateAccountRequest $request, BillingAccount $account, UpdateAccount $updateAccount): RedirectResponse
    {
        $updateAccount->handle($account, $request->toData($account->kind));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cuenta actualizada.']);

        return redirect()->route('billing.accounts.show', $account);
    }

    /** The month asked for the collected figure, as `2026-10`: a past one of the last twelve; null for this one, or one that is not. */
    private function month(string $asked): ?CarbonImmutable
    {
        return in_array($asked, array_column(array_slice($this->months(), 1), 'value'), true)
            ? CarbonImmutable::createFromFormat('!Y-m', $asked, config()->string('app.display_timezone')) ?: null
            : null;
    }

    /**
     * The last twelve months, this one first, to choose what came in.
     *
     * @return list<array{value: string, label: string}>
     */
    private function months(): array
    {
        $now = CarbonImmutable::now(config()->string('app.display_timezone'))->startOfMonth();

        return array_map(fn (int $back): array => ['value' => $now->subMonths($back)->format('Y-m'), 'label' => ShortDate::month($now->subMonths($back))], range(0, 11));
    }

    /**
     * The fixed SAT catalogs the account form chooses from.
     *
     * @return array{taxRegimes: list<array{value: string, label: string}>, cfdiUses: list<array{value: string, label: string}>}
     */
    private function satCatalogs(): array
    {
        return ['taxRegimes' => SatCatalogs::options(SatCatalogs::TAX_REGIMES), 'cfdiUses' => SatCatalogs::options(SatCatalogs::CFDI_USES)];
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
