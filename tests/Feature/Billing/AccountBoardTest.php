<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\StatementStatus;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\PaymentAllocation;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\DueDate;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

// An account's page as its board (tablero Cuenta de facturación) reads: what it billed and paid this year and how late it
// pays, when each statement was issued and is due, which months each payment settled, and who renews next.

mutates(DueDate::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-26 15:00:00');
});

/** An issued statement of the account for a month, for this many pesos, issued on the first of the month. */
function monthStatement(BillingAccount $account, string $month, int $pesos, array $overrides = []): Statement
{
    return Statement::factory()->issued()->create([
        'billing_account_id' => $account->id,
        'period_start' => "{$month}-01",
        'period_end' => CarbonImmutable::parse("{$month}-01")->addMonth()->toDateString(),
        'issued_at' => "{$month}-01 18:00:00",
        'subtotal_cents' => $pesos * 100,
        'vat_cents' => 0,
        'total_cents' => $pesos * 100,
        ...$overrides,
    ]);
}

/** A payment of the account on that day, applied whole to those statements (each takes what it owes). */
function settle(BillingAccount $account, string $paidOn, Statement ...$statements): Payment
{
    $payment = Payment::factory()->create(['billing_account_id' => $account->id, 'paid_on' => $paidOn, 'amount_cents' => array_sum(array_map(fn (Statement $statement): int => $statement->total_cents, $statements))]);

    foreach ($statements as $statement) {
        PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'statement_id' => $statement->id, 'amount_cents' => $statement->total_cents]);
    }

    return $payment;
}

it('says what the account billed and paid this year, and how many statements and payments that is', function (): void {
    $account = BillingAccount::factory()->create();
    monthStatement($account, '2026-09', 1_000);
    monthStatement($account, '2026-10', 2_000);
    monthStatement($account, '2025-12', 9_000);
    Statement::factory()->create(['billing_account_id' => $account->id, 'period_start' => '2026-11-01', 'period_end' => '2026-12-01', 'status' => StatementStatus::Draft]);
    Payment::factory()->create(['billing_account_id' => $account->id, 'paid_on' => '2026-03-02', 'amount_cents' => 50_000]);
    Payment::factory()->create(['billing_account_id' => $account->id, 'paid_on' => '2026-10-02', 'amount_cents' => 70_000]);
    Payment::factory()->create(['billing_account_id' => $account->id, 'paid_on' => '2025-12-30', 'amount_cents' => 10_000]);
    Payment::factory()->create(['billing_account_id' => $account->id, 'paid_on' => '2026-10-05', 'amount_cents' => 90_000, 'reversed_at' => now(), 'reversed_by' => superadmin()->id, 'reversal_reason' => 'Rebotó']);

    $this->actingAs(superadmin())->get(route('billing.accounts.show', $account))->assertInertia(fn (Assert $page) => $page
        ->where('year.year', 2026)
        ->where('year.billed', '$3,000')
        ->where('year.billed_statements', 2)
        ->where('year.paid', '$1,200')
        ->where('year.payments', 2));
});

it('averages how many days after it was due each statement settled this year was paid', function (): void {
    $account = BillingAccount::factory()->create();
    // Issued the 1st, due the 16th (15 days to pay): paid 4 days late, and 2 days early.
    settle($account, '2026-09-20', monthStatement($account, '2026-09', 1_000));
    settle($account, '2026-10-14', monthStatement($account, '2026-10', 1_000));
    // Settled last year, and one still owing: neither counts.
    settle($account, '2025-12-30', monthStatement($account, '2025-12', 1_000));
    monthStatement($account, '2026-11', 1_000);

    $this->actingAs(superadmin())->get(route('billing.accounts.show', $account))->assertInertia(fn (Assert $page) => $page
        ->where('year.average_days', 1));
});

it('has no average until a statement is settled', function (): void {
    $account = BillingAccount::factory()->create();
    monthStatement($account, '2026-11', 1_000);

    $this->actingAs(superadmin())->get(route('billing.accounts.show', $account))->assertInertia(fn (Assert $page) => $page
        ->where('year.average_days', null));
});

it('gives each statement the day it was issued and the day it is due, and a draft neither', function (): void {
    $account = BillingAccount::factory()->create();
    monthStatement($account, '2026-10', 1_000);
    Statement::factory()->create(['billing_account_id' => $account->id, 'period_start' => '2026-11-01', 'period_end' => '2026-12-01', 'status' => StatementStatus::Draft]);

    $this->actingAs(superadmin())->get(route('billing.accounts.show', $account))->assertInertia(fn (Assert $page) => $page
        ->where('statements.data.0.issued_on', null)
        ->where('statements.data.0.due_on', null)
        ->where('statements.data.1.issued_on', '1 oct 2026')
        ->where('statements.data.1.due_on', '16 oct 2026'));
});

it('lists only the statements still owing when asked for the open ones', function (): void {
    $account = BillingAccount::factory()->create();
    settle($account, '2026-09-20', monthStatement($account, '2026-09', 1_000));
    $owing = monthStatement($account, '2026-10', 1_000);
    Statement::factory()->create(['billing_account_id' => $account->id, 'period_start' => '2026-11-01', 'period_end' => '2026-12-01', 'status' => StatementStatus::Draft]);

    $this->actingAs(superadmin())->get(route('billing.accounts.show', [$account, 'statements' => 'open']))->assertInertia(fn (Assert $page) => $page
        ->where('statementsView', 'open')
        ->has('statements.data', 1)
        ->where('statements.data.0.id', $owing->id));

    $this->actingAs(superadmin())->get(route('billing.accounts.show', [$account, 'statements' => 'otro']))->assertInertia(fn (Assert $page) => $page
        ->where('statementsView', 'all')
        ->has('statements.data', 3));
});

it('says which months each payment settled', function (): void {
    $account = BillingAccount::factory()->create();
    $payment = settle($account, '2026-10-20', monthStatement($account, '2026-09', 1_000), monthStatement($account, '2026-10', 1_000));
    Payment::factory()->create(['billing_account_id' => $account->id, 'paid_on' => '2026-10-01']);

    $this->actingAs(superadmin())->get(route('billing.accounts.show', $account))->assertInertia(fn (Assert $page) => $page
        ->where('payments.data.0.id', $payment->id)
        ->where('payments.data.0.applied_to', ['sep 2026', 'oct 2026'])
        ->where('payments.data.1.applied_to', []));
});

it('names the company that renews next', function (): void {
    $account = BillingAccount::factory()->consultant()->create();

    foreach (['Farmacias del Norte' => '2026-12-07', 'Taller Bravo' => '2027-02-01'] as $name => $renews) {
        $tenant = Tenant::factory()->create(['trade_name' => $name]);
        AccountCompany::factory()->for($account, 'account')->create(['tenant_id' => $tenant->id]);
        Subscription::factory()->create(['tenant_id' => $tenant->id, 'starts_on' => '2026-01-01', 'renews_on' => $renews]);
    }

    $this->actingAs(superadmin())->get(route('billing.accounts.show', $account))->assertInertia(fn (Assert $page) => $page
        ->where('summary.next_renewal', '7 dic 2026')
        ->where('summary.next_renewal_company', 'Farmacias del Norte'));
});
