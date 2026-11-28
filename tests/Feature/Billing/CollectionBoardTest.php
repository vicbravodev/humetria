<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Models\Subscription;
use Inertia\Testing\AssertableInertia as Assert;

// The collection page as its board (tablero Cuentas y cobranza) reads: the month collected against the one before, what the
// renewals are worth a year, and the filters by kind, age of the balance and month.

beforeEach(function (): void {
    $this->travelTo('2026-11-26 15:00:00');
});

function owingStatement(BillingAccount $account, int $pesos, int $daysAgo): Statement
{
    return Statement::factory()->issued()->create(['billing_account_id' => $account->id, 'subtotal_cents' => $pesos * 100, 'vat_cents' => 0, 'total_cents' => $pesos * 100, 'issued_at' => now()->subDays($daysAgo)]);
}

it('compares what came in this month with the month before, in whole percent', function (): void {
    Payment::factory()->create(['amount_cents' => 150_000, 'paid_on' => '2026-11-03']);
    Payment::factory()->create(['amount_cents' => 100_000, 'paid_on' => '2026-10-31']);
    Payment::factory()->create(['amount_cents' => 900_000, 'paid_on' => '2026-09-15']);

    $this->actingAs(superadmin())->get(route('billing.accounts.index'))->assertInertia(fn (Assert $page) => $page
        ->where('receivable.collected', '$1,500')
        ->where('receivable.collected_change', 50)
        ->where('receivable.previous_month', 'octubre'));
});

it('says nothing about the change when nothing came in the month before', function (): void {
    Payment::factory()->create(['amount_cents' => 150_000, 'paid_on' => '2026-11-03']);

    $this->actingAs(superadmin())->get(route('billing.accounts.index'))->assertInertia(fn (Assert $page) => $page
        ->where('receivable.collected_change', null));
});

it('reads the collected figure of the month asked, and ignores a month that is not one or is still to come', function (string $month, string $collected, string $name): void {
    Payment::factory()->create(['amount_cents' => 150_000, 'paid_on' => '2026-11-03']);
    Payment::factory()->create(['amount_cents' => 100_000, 'paid_on' => '2026-10-31']);

    $this->actingAs(superadmin())->get(route('billing.accounts.index', ['month' => $month]))->assertInertia(fn (Assert $page) => $page
        ->where('receivable.collected', $collected)
        ->where('receivable.month', $name)
        ->where('filters.month', $collected === '$1,000' ? '2026-10' : ''));
})->with([
    'a past month' => ['2026-10', '$1,000', 'octubre'],
    'not a month' => ['octubre', '$1,500', 'noviembre'],
    'a month to come' => ['2026-12', '$1,500', 'noviembre'],
]);

it('offers the last twelve months to choose from, the current one first', function (): void {
    $this->actingAs(superadmin())->get(route('billing.accounts.index'))->assertInertia(fn (Assert $page) => $page
        ->has('months', 12)
        ->where('months.0', ['value' => '2026-11', 'label' => 'nov 2026'])
        ->where('months.11', ['value' => '2025-12', 'label' => 'dic 2025']));
});

it('says what the plans that renew in 30 days are worth a year, before VAT', function (): void {
    Subscription::factory()->plan(Plan::Cumplimiento)->create(['seats' => 100, 'starts_on' => '2026-11-01', 'renews_on' => '2026-12-01']);
    Subscription::factory()->plan(Plan::Cumplimiento)->create(['seats' => 100, 'starts_on' => '2026-11-01', 'renews_on' => '2027-03-01']);

    $this->actingAs(superadmin())->get(route('billing.accounts.index'))->assertInertia(fn (Assert $page) => $page
        ->where('receivable.renewing', 1)
        ->where('receivable.renewing_yearly', '$58,800'));
});

it('narrows a view by kind of account', function (): void {
    $consultant = BillingAccount::factory()->consultant()->create();
    owingStatement($consultant, 1_000, 30);
    owingStatement(BillingAccount::factory()->create(), 1_000, 30);

    $this->actingAs(superadmin())->get(route('billing.accounts.index', ['kind' => 'overdue', 'type' => 'consultant']))->assertInertia(fn (Assert $page) => $page
        ->where('filters.type', 'consultant')
        ->has('accounts.data', 1)
        ->where('accounts.data.0.id', $consultant->id));
});

it('narrows the accounts by the age of their oldest open balance', function (string $age, int $days): void {
    $match = BillingAccount::factory()->create();
    owingStatement($match, 1_000, $days);
    owingStatement(BillingAccount::factory()->create(), 1_000, $age === 'current' ? 50 : 3);
    BillingAccount::factory()->create();

    $this->actingAs(superadmin())->get(route('billing.accounts.index', ['age' => $age]))->assertInertia(fn (Assert $page) => $page
        ->where('filters.age', $age)
        ->has('accounts.data', 1)
        ->where('accounts.data.0.id', $match->id));
})->with([
    'current' => ['current', 3],
    'the last day current' => ['current', 14],
    'the first day past due' => ['1_30', 15],
    'day 30' => ['1_30', 44],
    'day 31' => ['31_60', 45],
    'day 60' => ['31_60', 74],
    'day 61' => ['over_60', 75],
]);

it('ignores an age that is not one', function (): void {
    BillingAccount::factory()->count(2)->create();

    $this->actingAs(superadmin())->get(route('billing.accounts.index', ['age' => 'viejo']))->assertInertia(fn (Assert $page) => $page
        ->where('filters.age', '')
        ->has('accounts.data', 2));
});

it('gives each account what it owes in cents, for the sum of a selection', function (): void {
    $account = BillingAccount::factory()->create();
    owingStatement($account, 1_200, 3);

    $this->actingAs(superadmin())->get(route('billing.accounts.index'))->assertInertia(fn (Assert $page) => $page
        ->where('accounts.data.0.outstanding_cents', 120_000));
});
