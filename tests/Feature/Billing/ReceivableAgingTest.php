<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\PaymentAllocation;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Models\Subscription;
use Inertia\Testing\AssertableInertia as Assert;

/** An issued statement of the account for this many pesos, issued that many days (and hours) ago. */
function agedStatement(BillingAccount $account, int $pesos, int $days, int $hours = 0): Statement
{
    return Statement::factory()->issued()->create(['billing_account_id' => $account->id, 'subtotal_cents' => $pesos * 100, 'vat_cents' => 0, 'total_cents' => $pesos * 100, 'issued_at' => now()->subDays($days)->subHours($hours)]);
}

beforeEach(function (): void {
    $this->travelTo('2026-11-26 15:00:00');
});

it('splits what the accounts owe by how long it is past due, and the parts add up to the whole', function (): void {
    $account = BillingAccount::factory()->create();
    agedStatement($account, 1_000, 14);        // due tomorrow: al corriente
    agedStatement($account, 2_000, 15);        // the first day past due
    agedStatement($account, 3_000, 44);        // day 30
    agedStatement($account, 4_000, 45);        // day 31
    agedStatement($account, 5_000, 74);        // day 60
    agedStatement($account, 6_000, 75);        // day 61
    $partly = agedStatement(BillingAccount::factory()->create(), 700, 20);
    PaymentAllocation::factory()->create(['payment_id' => Payment::factory()->create(['billing_account_id' => $partly->billing_account_id, 'amount_cents' => 20_000])->id, 'statement_id' => $partly->id, 'amount_cents' => 20_000]);
    Statement::factory()->create(['billing_account_id' => $account->id]); // a draft owes nothing yet

    $this->actingAs(superadmin())->get(route('billing.accounts.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('receivable.outstanding', '$21,500')
        ->where('receivable.overdue', '$20,500')
        ->where('receivable.open_statements', 7)
        ->where('receivable.overdue_accounts', 2)
        ->where('receivable.aging', [
            ['key' => 'current', 'amount' => '$1,000', 'share' => 5],
            ['key' => '1_30', 'amount' => '$5,500', 'share' => 26],
            ['key' => '31_60', 'amount' => '$9,000', 'share' => 42],
            ['key' => 'over_60', 'amount' => '$6,000', 'share' => 28],
        ]));
});

it('counts what the standing payments brought in this month, in the panel time zone, and leaves out a reversed one', function (): void {
    Payment::factory()->create(['amount_cents' => 100_000, 'paid_on' => '2026-11-01']);
    Payment::factory()->create(['amount_cents' => 50_000, 'paid_on' => '2026-11-26']);
    Payment::factory()->create(['amount_cents' => 70_000, 'paid_on' => '2026-10-31']);
    Payment::factory()->create(['amount_cents' => 90_000, 'paid_on' => '2026-11-10', 'reversed_at' => now(), 'reversed_by' => superadmin()->id, 'reversal_reason' => 'Rebotó']);

    $this->actingAs(superadmin())->get(route('billing.accounts.index'))->assertInertia(fn (Assert $page) => $page
        ->where('receivable.collected', '$1,500')
        ->where('receivable.month', 'noviembre'));
});

it('counts the plans that renew in the next 30 days, never a trial, one that ends or one that is cancelling', function (): void {
    Subscription::factory()->create(['starts_on' => '2026-11-01', 'renews_on' => '2026-12-01']);
    Subscription::factory()->status(SubscriptionStatus::PastDue)->create(['starts_on' => '2026-11-01', 'renews_on' => '2026-12-26']);
    Subscription::factory()->create(['starts_on' => '2026-11-01', 'renews_on' => '2026-12-27']);
    Subscription::factory()->status(SubscriptionStatus::CancelScheduled)->create(['starts_on' => '2026-11-01', 'renews_on' => '2026-12-01']);
    Subscription::factory()->status(SubscriptionStatus::Trial)->create(['starts_on' => '2026-11-01', 'renews_on' => '2026-12-01']);
    Subscription::factory()->status(SubscriptionStatus::Ended)->create(['starts_on' => '2026-11-01', 'renews_on' => '2026-12-01']);

    $this->actingAs(superadmin())->get(route('billing.accounts.index'))->assertInertia(fn (Assert $page) => $page->where('receivable.renewing', 2));
});

it('says nothing is owed when no statement is open', function (): void {
    $this->actingAs(superadmin())->get(route('billing.accounts.index'))->assertInertia(fn (Assert $page) => $page
        ->where('receivable.outstanding', '$0')
        ->where('receivable.overdue', '$0')
        ->where('receivable.open_statements', 0)
        ->where('receivable.aging.0', ['key' => 'current', 'amount' => '$0', 'share' => 0]));
});

it('gives each account of the page what it owes, how old its oldest open statement is and its last payment', function (): void {
    $late = BillingAccount::factory()->create(['created_at' => now()->subDays(3)]);
    agedStatement($late, 3_000, 50);
    agedStatement($late, 1_000, 5);
    Payment::factory()->create(['billing_account_id' => $late->id, 'paid_on' => '2026-10-02']);
    Payment::factory()->create(['billing_account_id' => $late->id, 'paid_on' => '2026-09-01']);
    $soon = BillingAccount::factory()->create(['created_at' => now()->subDays(2)]);
    agedStatement($soon, 2_000, 3);
    $clean = BillingAccount::factory()->create(['created_at' => now()->subDay()]);

    $this->actingAs(superadmin())->get(route('billing.accounts.index'))->assertInertia(fn (Assert $page) => $page
        ->where('accounts.data.0.id', $clean->id)
        ->where('accounts.data.0.outstanding', null)
        ->where('accounts.data.0.aging', null)
        ->where('accounts.data.0.last_payment', null)
        ->where('accounts.data.1.id', $soon->id)
        ->where('accounts.data.1.outstanding', '$2,000')
        ->where('accounts.data.1.overdue', null)
        ->where('accounts.data.1.aging', ['bucket' => 'current', 'days' => 12])
        ->where('accounts.data.2.id', $late->id)
        ->where('accounts.data.2.outstanding', '$4,000')
        ->where('accounts.data.2.overdue', '$3,000')
        ->where('accounts.data.2.aging', ['bucket' => '31_60', 'days' => 36])
        ->where('accounts.data.2.last_payment', '2 oct 2026'));
});

it('lists as one view the accounts with something to collect, due or not, and counts them', function (): void {
    $owing = BillingAccount::factory()->create();
    agedStatement($owing, 1_000, 3);
    $late = BillingAccount::factory()->create();
    agedStatement($late, 1_000, 30);
    BillingAccount::factory()->create();

    $this->actingAs(superadmin())->get(route('billing.accounts.index', ['kind' => 'receivable']))->assertInertia(fn (Assert $page) => $page
        ->where('filters.kind', 'receivable')
        ->where('counts.receivable', 2)
        ->where('counts.overdue', 1)
        ->where('counts.all', 3)
        ->has('accounts.data', 2));
});

it('reads the whole collection page in a fixed number of queries, whatever the number of accounts', function (): void {
    foreach (range(1, 6) as $day) {
        $account = BillingAccount::factory()->create();
        agedStatement($account, 1_000, $day * 10);
        Payment::factory()->create(['billing_account_id' => $account->id]);
    }

    $user = superadmin();
    $this->actingAs($user);
    $this->expectsDatabaseQueryCount(15);

    $this->get(route('billing.accounts.index'))->assertOk();
});

it('tells the account page what it owes past due and how old its oldest open statement is, for its head', function (): void {
    $account = BillingAccount::factory()->create();
    agedStatement($account, 3_000, 50);
    agedStatement($account, 1_000, 5);
    $clean = BillingAccount::factory()->create();

    $this->actingAs(superadmin())->get(route('billing.accounts.show', $account))->assertInertia(fn (Assert $page) => $page
        ->where('receivable', ['outstanding' => '$4,000', 'open_statements' => 2, 'overdue' => '$3,000', 'aging' => ['bucket' => '31_60', 'days' => 36]]));
    $this->get(route('billing.accounts.show', $clean))->assertInertia(fn (Assert $page) => $page
        ->where('receivable', ['outstanding' => '$0', 'open_statements' => 0, 'overdue' => null, 'aging' => null]));
});
