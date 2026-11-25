<?php

declare(strict_types=1);

use App\Modules\Billing\Actions\SetSubscription;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\Profile;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\SubscriptionGuard;
use App\Modules\Tenancy\Contracts\CompanyLimits;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\ModuleStates;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia as Assert;

mutates(SetSubscription::class, SubscriptionGuard::class);

/** @return array{0: Tenant, 1: BillingAccount} A union paid by a new direct account. */
function paidUnion(): array
{
    $account = BillingAccount::factory()->create();
    $tenant = Tenant::factory()->union()->create();
    AccountCompany::factory()->for($account, 'account')->create(['tenant_id' => $tenant->id]);

    return [$tenant, $account];
}

/** @param array<string, mixed> $overrides */
function unionInput(array $overrides = []): array
{
    return subscriptionInput(['plan' => 'sindicato', 'profile' => 'union', 'seats' => 0, 'centers' => 1, ...$overrides]);
}

it('gives a union the Sindicato plan with its flat fee, and nothing but Compensación', function (): void {
    // What a plan takes away ends the day it starts: the plan started on the 1st.
    $this->travelTo('2026-12-10 12:00:00');
    [$union] = paidUnion();

    $this->actingAs(superadmin())->put(route('billing.subscriptions.update', $union->id), unionInput())->assertSessionHasNoErrors();

    resolve(ModuleStates::class)->forget();
    $subscription = Subscription::query()->sole();

    expect($subscription->plan)->toBe(Plan::Sindicato)
        ->and($subscription->profile)->toBe(Profile::Union)
        ->and(resolve(CompanyModules::class)->enabledKeys($union->id))->toBe(['compensation']);
});

it('puts no limit of people or centers on a union: its fee is the same for everyone', function (): void {
    [$union] = paidUnion();

    $this->actingAs(superadmin())->put(route('billing.subscriptions.update', $union->id), unionInput(['seats' => 900, 'centers' => 4]))->assertSessionHasNoErrors();

    $subscription = Subscription::query()->sole();
    expect($subscription->seats)->toBe(0)->and($subscription->centers)->toBe(1)
        ->and(resolve(CompanyLimits::class)->usage($union->id))->toBe([]);
});

it('activates a union with no people counted, since its fee does not depend on them', function (): void {
    [$union] = paidUnion();

    $this->actingAs(superadmin())->put(route('billing.subscriptions.update', $union->id), unionInput(['status' => 'active']))->assertSessionHasNoErrors();
});

it('refuses a union any plan but Sindicato', function (string $plan, string $profile): void {
    [$union] = paidUnion();

    $this->actingAs(superadmin())->put(route('billing.subscriptions.update', $union->id), subscriptionInput(['plan' => $plan, 'profile' => $profile]))
        ->assertSessionHasErrors(['plan' => 'Un sindicato solo puede tener el plan Sindicato.']);

    expect(Subscription::query()->count())->toBe(0);
})->with([['jornada', 'direct'], ['cumplimiento', 'direct'], ['integral', 'direct'], ['enterprise', 'direct']]);

it('refuses a company the Sindicato plan', function (): void {
    [$company] = paidCompany();

    $this->actingAs(superadmin())->put(route('billing.subscriptions.update', $company->id), unionInput())
        ->assertSessionHasErrors(['plan' => 'El plan Sindicato es solo para sindicatos.']);

    expect(Subscription::query()->count())->toBe(0);
});

it('lets a union have a negotiated price on its plan', function (): void {
    [$union] = paidUnion();

    $this->actingAs(superadmin())->put(route('billing.subscriptions.update', $union->id), unionInput(['profile' => 'negotiated', 'negotiated_price' => '2500']))->assertSessionHasNoErrors();

    expect(Subscription::query()->sole())->negotiated_price_cents->toBe(250_000);
});

it('previews the plan of a union with its fee and the one module it opens', function (): void {
    [$union] = paidUnion();

    $this->actingAs(superadmin())->postJson(route('billing.subscriptions.preview', $union->id), unionInput())
        ->assertOk()
        ->assertJsonPath('quote.lines.0.label', 'Sindicato')
        ->assertJsonPath('quote.lines.0.amount', '$1,990')
        ->assertJsonPath('quote.lines.0.explanation', 'Cuota fija mensual: $1,990');
});

it('makes the statement of a union: its flat fee and the VAT', function (): void {
    [$union] = paidUnion();
    $this->actingAs(superadmin())->put(route('billing.subscriptions.update', $union->id), unionInput())->assertSessionHasNoErrors();

    $this->postJson(route('billing.statements.preview', $union->id), ['period' => '2026-12'])
        ->assertOk()
        ->assertJsonPath('lines.0.label', 'Sindicato')
        ->assertJsonPath('subtotal', '$1,990')
        ->assertJsonPath('vat', '$318.40')
        ->assertJsonPath('total', '$2,308.40');

    expect(Statement::query()->count())->toBe(0);
});

it('lets the database refuse the flat fee on a company plan, and the union plan with a company rate', function (string $plan, string $profile): void {
    [$union] = paidUnion();

    expect(fn () => Subscription::factory()->create(['tenant_id' => $union->id, 'plan' => $plan, 'profile' => $profile]))->toThrow(QueryException::class);
})->with([['integral', 'union'], ['sindicato', 'direct'], ['sindicato', 'channel_integral']]);

it('tells the console which company of an account is a union, so its form offers its plan', function (): void {
    [$union, $account] = paidUnion();
    $company = Tenant::factory()->create();
    AccountCompany::factory()->for($account, 'account')->create(['tenant_id' => $company->id]);

    $this->actingAs(superadmin())->get(route('billing.accounts.show', $account))->assertInertia(fn (Assert $page) => $page
        ->where('companies.data', fn ($rows): bool => collect($rows)->pluck('union', 'company_id')->sortKeys()->all() === collect([$union->id => true, $company->id => false])->sortKeys()->all()));
});
