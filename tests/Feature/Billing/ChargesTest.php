<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Billing\Actions\AddCharge;
use App\Modules\Billing\Actions\ListAccountCharges;
use App\Modules\Billing\Enums\ChargeKind;
use App\Modules\Billing\Http\Requests\StoreChargeRequest;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\SubscriptionCharge;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;

mutates(AddCharge::class, ListAccountCharges::class, StoreChargeRequest::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function chargeInput(array $overrides = []): array
{
    return [
        'kind' => 'one_time',
        'description' => 'Implementación',
        'scope' => 'Alta de 3 centros y carga de plantilla',
        'amount' => '12500',
        'charge_on' => '2026-12-15',
        'ends_on' => '',
        ...$overrides,
    ];
}

/** A company with a plan, paid by an account. */
function companyWithPlan(): array
{
    [$tenant, $account] = paidCompany();
    $subscription = Subscription::factory()->create(['tenant_id' => $tenant->id, 'profile' => App\Modules\Billing\Enums\Profile::Direct]);

    return [$tenant, $account, $subscription];
}

it('lives under /admin/billing/companies/{company}/charges', function (): void {
    expect(route('billing.charges.store', 'abc', absolute: false))->toBe('/admin/billing/companies/abc/charges');
});

it('adds a one-time charge, approved by whoever adds it, and logs and audits it', function (): void {
    Log::spy();
    [$tenant, $account, $subscription] = companyWithPlan();
    $admin = superadmin();

    $this->actingAs($admin)->post(route('billing.charges.store', $tenant->id), chargeInput())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('billing.accounts.show', $account))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Cargo agregado.']]);

    $charge = SubscriptionCharge::query()->sole();

    expect($charge->subscription_id)->toBe($subscription->id)
        ->and($charge->tenant_id)->toBe($tenant->id)
        ->and($charge->kind)->toBe(ChargeKind::OneTime)
        ->and($charge->description)->toBe('Implementación')
        ->and($charge->scope)->toBe('Alta de 3 centros y carga de plantilla')
        ->and($charge->amount_cents)->toBe(1_250_000)
        ->and($charge->charge_on->toDateString())->toBe('2026-12-15')
        ->and($charge->ends_on)->toBeNull()
        ->and($charge->approved_by)->toBe($admin->id);

    Log::shouldHaveReceived('info')->once()->with('billing.charge_added', ['tenant_id' => $tenant->id, 'kind' => 'one_time']);
    $entry = AuditEntry::query()->where('event', 'billing.charge_added')->sole();
    expect($entry->subject_id)->toBe($charge->id)
        ->and($entry->metadata)->toEqualCanonicalizing(['tenant_id' => $tenant->id, 'kind' => 'one_time', 'amount_cents' => 1_250_000, 'charge_on' => '2026-12-15']);
});

it('adds a recurring charge, like the accompaniment, with the day it ends if it does', function (): void {
    [$tenant] = companyWithPlan();

    $this->actingAs(superadmin())->post(route('billing.charges.store', $tenant->id), chargeInput(['kind' => 'recurring', 'description' => 'Acompañamiento', 'amount' => '1500.50', 'ends_on' => '2027-05-31']))
        ->assertSessionHasNoErrors();

    expect(SubscriptionCharge::query()->sole())
        ->kind->toBe(ChargeKind::Recurring)
        ->amount_cents->toBe(150_050)
        ->and(SubscriptionCharge::query()->sole()->ends_on?->toDateString())->toBe('2027-05-31');
});

it('charges from today when no day is given', function (): void {
    [$tenant] = companyWithPlan();

    $this->actingAs(superadmin())->post(route('billing.charges.store', $tenant->id), chargeInput(['charge_on' => '']))->assertSessionHasNoErrors();

    expect(SubscriptionCharge::query()->sole()->charge_on->toDateString())->toBe(now(config()->string('app.display_timezone'))->toDateString());
});

it('refuses a charge for a company with no plan, telling to assign one first', function (): void {
    [$tenant] = paidCompany();

    $this->actingAs(superadmin())->post(route('billing.charges.store', $tenant->id), chargeInput())
        ->assertSessionHasErrors(['kind' => 'Asigna un plan a la empresa antes de agregarle cargos.']);

    expect(SubscriptionCharge::query()->count())->toBe(0)->and(AuditEntry::query()->where('event', 'billing.charge_added')->count())->toBe(0);
});

it('refuses what the table would refuse, with a message', function (array $overrides, string $field): void {
    [$tenant] = companyWithPlan();

    $this->actingAs(superadmin())->post(route('billing.charges.store', $tenant->id), chargeInput($overrides))->assertSessionHasErrors($field);

    expect(SubscriptionCharge::query()->count())->toBe(0);
})->with([
    'no kind' => [['kind' => ''], 'kind'],
    'an unknown kind' => [['kind' => 'regalo'], 'kind'],
    'no description' => [['description' => ''], 'description'],
    'a description of two lines' => [['description' => "uno\ndos"], 'description'],
    'a description that is too long' => [['description' => str_repeat('a', 201)], 'description'],
    'no scope' => [['scope' => ''], 'scope'],
    'a scope that is too long' => [['scope' => str_repeat('a', 201)], 'scope'],
    'no amount' => [['amount' => ''], 'amount'],
    'an amount of zero' => [['amount' => '0'], 'amount'],
    'an amount of zero cents' => [['amount' => '0.00'], 'amount'],
    'an amount with a comma' => [['amount' => '1,500'], 'amount'],
    'a negative amount' => [['amount' => '-5'], 'amount'],
    'an amount of three decimals' => [['amount' => '1.005'], 'amount'],
    'a day that is not a date' => [['charge_on' => 'pronto'], 'charge_on'],
    'an end day on a one-time charge' => [['kind' => 'one_time', 'ends_on' => '2027-01-31'], 'ends_on'],
    'an end day before the start' => [['kind' => 'recurring', 'ends_on' => '2026-12-14'], 'ends_on'],
]);

it('is the superadmin\'s alone: support and everyone else get a 404 and nothing is added', function (): void {
    [$tenant] = companyWithPlan();

    $this->actingAs(support())->post(route('billing.charges.store', $tenant->id), chargeInput())->assertNotFound();
    $this->actingAs(ownerOf($tenant))->post(route('billing.charges.store', $tenant->id), chargeInput())->assertNotFound();

    expect(SubscriptionCharge::query()->count())->toBe(0);
});

it('answers 404 for a company that does not exist', function (): void {
    $this->actingAs(superadmin())->post(route('billing.charges.store', '11111111-1111-4111-8111-111111111111'), chargeInput())->assertNotFound();
    $this->actingAs(superadmin())->post('/admin/billing/companies/no-es-uuid/charges', chargeInput())->assertNotFound();
});

it('keeps the route out of reach while impersonating', function (): void {
    expect(App\Http\Middleware\ForbidWhileImpersonating::guards('billing.charges.store'))->toBeTrue();
});

it('lists the charges of the companies the account pays now, newest first, a page at a time, with who approved them', function (): void {
    [$tenant, $account, $subscription] = companyWithPlan();
    $approver = User::factory()->create(['name' => 'Victor Bravo']);
    $old = SubscriptionCharge::factory()->for($subscription)->create(['tenant_id' => $tenant->id, 'charge_on' => '2026-12-01', 'approved_by' => $approver->id, 'description' => 'Implementación', 'amount_cents' => 1_250_000]);
    $new = SubscriptionCharge::factory()->recurring()->for($subscription)->create(['tenant_id' => $tenant->id, 'charge_on' => '2026-12-20', 'ends_on' => '2027-05-31', 'description' => 'Acompañamiento', 'amount_cents' => 150_050]);
    // Another account's charge, and one of a company this account stopped paying.
    SubscriptionCharge::factory()->create();
    [$former, , $formerSubscription] = companyWithPlan();
    App\Modules\Billing\Models\AccountCompany::query()->where('tenant_id', $former->id)->update(['billing_account_id' => $account->id, 'ends_on' => '2026-12-31']);
    SubscriptionCharge::factory()->for($formerSubscription)->create(['tenant_id' => $former->id]);

    $this->actingAs(superadmin())->get(route('billing.accounts.show', $account))
        ->assertInertia(fn (Assert $page) => $page
            ->has('charges.data', 2)
            ->where('charges.total', 2)
            ->hasAll(['charges.current_page', 'charges.last_page', 'charges.per_page', 'charges.links'])
            ->where('charges.data.0.id', $new->id)
            ->where('charges.data.0.kind', 'recurring')
            ->where('charges.data.0.kind_label', 'Recurrente')
            ->where('charges.data.0.description', 'Acompañamiento')
            ->where('charges.data.0.amount', '$1,500.50')
            ->where('charges.data.0.charge_on', '20 dic 2026')
            ->where('charges.data.0.ends_on', '31 may 2027')
            ->where('charges.data.1.id', $old->id)
            ->where('charges.data.1.ends_on', null)
            ->where('charges.data.1.scope', 'Una empresa')
            ->where('charges.data.1.approved_by', 'Victor Bravo')
            ->where('charges.data.1.trade_name', $tenant->trade_name)
            ->where('charges.data.1.company_id', $tenant->id));
});

it('pages the charges 25 at a time', function (): void {
    [$tenant, $account, $subscription] = companyWithPlan();
    SubscriptionCharge::factory()->count(26)->for($subscription)->create(['tenant_id' => $tenant->id]);

    $this->actingAs(superadmin())->get(route('billing.accounts.show', $account))
        ->assertInertia(fn (Assert $page) => $page->has('charges.data', 25)->where('charges.total', 26));
});

it('lets support read the charges, and leaves them out of the totals of the account', function (): void {
    [$tenant, $account, $subscription] = companyWithPlan();
    SubscriptionCharge::factory()->recurring()->for($subscription)->create(['tenant_id' => $tenant->id]);

    $this->actingAs(support())->get(route('billing.accounts.show', $account))
        ->assertInertia(fn (Assert $page) => $page->has('charges.data', 1)->where('summary.subtotal', '$4,900'));
});

it('does not give away the charges of an account that is not the one asked for', function (): void {
    [, $account] = companyWithPlan();
    $other = BillingAccount::factory()->create();
    [, , $subscription] = companyWithPlan();
    SubscriptionCharge::factory()->for($subscription)->create(['tenant_id' => $subscription->tenant_id]);

    $this->actingAs(superadmin())->get(route('billing.accounts.show', $other))
        ->assertInertia(fn (Assert $page) => $page->has('charges.data', 0));
    $this->get(route('billing.accounts.show', $account))->assertInertia(fn (Assert $page) => $page->has('charges.data', 0));
});

it('leaves the company with no tenant row to touch: adding a charge never changes the company', function (): void {
    [$tenant] = companyWithPlan();
    $before = $tenant->refresh()->getAttributes();

    $this->actingAs(superadmin())->post(route('billing.charges.store', $tenant->id), chargeInput())->assertSessionHasNoErrors();

    expect($tenant->refresh()->getAttributes())->toBe($before)->and(Tenant::query()->count())->toBe(1);
});
