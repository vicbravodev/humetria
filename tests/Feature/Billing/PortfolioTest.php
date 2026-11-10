<?php

declare(strict_types=1);

use App\Modules\Billing\Actions\ListAccountCompanies;
use App\Modules\Billing\Actions\SummarizeAccount;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\Profile;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\Eligibility;
use App\Modules\Billing\Support\QuoteView;
use App\Modules\Billing\Support\SubscriptionQuoter;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

mutates(SummarizeAccount::class, ListAccountCompanies::class, Eligibility::class, SubscriptionQuoter::class, QuoteView::class);

function summaryOf(BillingAccount $account): array
{
    $page = test()->actingAs(superadmin())->get(route('billing.accounts.show', $account))->viewData('page');

    return $page['props']['summary'];
}

it('prices a consultant\'s five companies of 100 people as the spec says: five lines of $3,990 and $19,950', function (): void {
    $account = BillingAccount::factory()->consultant()->create();
    foreach (range(1, 5) as $ignored) {
        planFor($account);
    }

    $this->actingAs(superadmin())->get(route('billing.accounts.show', $account))
        ->assertInertia(fn (Assert $page) => $page
            ->has('companies.data', 5)
            ->where('companies.data.0.subscription.quote.subtotal', '$3,990')
            ->where('companies.data.4.subscription.quote.subtotal', '$3,990')
            ->where('summary.companies', 5)
            ->where('summary.with_plan', 5)
            ->where('summary.without_plan', 0)
            ->where('summary.subtotal', '$19,950')
            ->where('summary.vat', '$3,192')
            ->where('summary.total', '$23,142')
            ->where('summary.requiring_quotation', 0)
            ->where('summary.discount', null));
});

it('shows what a company contracted and what it costs, line by line', function (): void {
    $account = BillingAccount::factory()->create();
    $tenant = Tenant::factory()->create();
    WorkCenter::factory()->for($tenant)->count(4)->create();
    staffOf($tenant, WorkCenter::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail(), 3, 2);
    planFor($account, ['plan' => Plan::Integral, 'profile' => Profile::Direct, 'seats' => 100, 'centers' => 4, 'starts_on' => '2026-12-01', 'renews_on' => '2027-01-01'], $tenant);

    $this->actingAs(superadmin())->get(route('billing.accounts.show', $account))
        ->assertInertia(fn (Assert $page) => $page
            ->where('companies.data.0.subscription.plan', 'integral')
            ->where('companies.data.0.subscription.plan_label', 'Integral')
            ->where('companies.data.0.subscription.profile', 'direct')
            ->where('companies.data.0.subscription.profile_label', 'Directo')
            ->where('companies.data.0.subscription.status', 'active')
            ->where('companies.data.0.subscription.status_label', 'Activa')
            ->where('companies.data.0.subscription.seats', 100)
            ->where('companies.data.0.subscription.centers', 4)
            ->where('companies.data.0.people', 5)
            ->where('companies.data.0.subscription.starts_on', '2026-12-01')
            ->where('companies.data.0.subscription.renews_on', '1 ene 2027')
            ->where('companies.data.0.subscription.catalog_version', 'v1')
            ->where('companies.data.0.subscription.negotiated_price', '')
            ->where('companies.data.0.subscription.negotiated_valid_until', '')
            ->where('companies.data.0.subscription.quote.requires_quotation', false)
            ->where('companies.data.0.subscription.quote.subtotal', '$10,049')
            ->where('companies.data.0.subscription.quote.vat', '$1,607.84')
            ->where('companies.data.0.subscription.quote.total', '$11,656.84')
            ->where('companies.data.0.subscription.quote.lines.0.label', 'Integral')
            ->where('companies.data.0.subscription.quote.lines.0.amount', '$9,900')
            ->where('companies.data.0.subscription.quote.lines.0.explanation', '100 personas × $99 = $9,900')
            ->where('companies.data.0.subscription.quote.lines.1.amount', '$149'));
});

it('gives the channel Integral discount from three companies with paid Integral, and says why', function (): void {
    $account = BillingAccount::factory()->consultant()->create();
    foreach (range(1, 3) as $ignored) {
        planFor($account, ['plan' => Plan::Integral, 'profile' => Profile::ChannelIntegral, 'centers' => 3]);
    }

    $this->actingAs(superadmin())->get(route('billing.accounts.show', $account))
        ->assertInertia(fn (Assert $page) => $page
            ->where('companies.data.0.subscription.quote.subtotal', '$7,920')
            ->where('summary.subtotal', '$23,760')
            ->where('summary.discount', ['applies' => true, 'reason' => 'Descuento de 20% aplicado: 3 empresas con Integral pagado (el canal pide 3).']));
});

it('charges the direct rate with two companies of paid Integral, and says how many are missing', function (): void {
    $account = BillingAccount::factory()->consultant()->create();
    foreach (range(1, 2) as $ignored) {
        planFor($account, ['plan' => Plan::Integral, 'profile' => Profile::ChannelIntegral, 'centers' => 3]);
    }

    $this->actingAs(superadmin())->get(route('billing.accounts.show', $account))
        ->assertInertia(fn (Assert $page) => $page
            ->where('companies.data.0.subscription.quote.subtotal', '$9,900')
            ->where('summary.subtotal', '$19,800')
            ->where('summary.discount', ['applies' => false, 'reason' => 'Sin descuento: 2 de las 3 empresas con Integral pagado que pide el canal. Pagan la tarifa directa; con 1 más, el descuento es de 20%.']));
});

it('shows no discount reason for an account that has no channel Integral plans', function (): void {
    $account = BillingAccount::factory()->consultant()->create();
    planFor($account);

    expect(summaryOf($account)['discount'])->toBeNull();
});

it('counts for the discount only the companies that pay Integral now: not trials, not the ones past due, ended, suspended or archived, not other plans or other accounts', function (): void {
    $account = BillingAccount::factory()->consultant()->create();
    $other = BillingAccount::factory()->consultant()->create();
    $integral = ['plan' => Plan::Integral, 'profile' => Profile::ChannelIntegral, 'centers' => 3];

    planFor($account, [...$integral, 'status' => SubscriptionStatus::Active]);
    planFor($account, [...$integral, 'status' => SubscriptionStatus::CancelScheduled]);
    planFor($account, [...$integral, 'status' => SubscriptionStatus::Trial]);
    planFor($account, [...$integral, 'status' => SubscriptionStatus::PastDue]);
    planFor($account, [...$integral, 'status' => SubscriptionStatus::Ended]);
    planFor($account, [...$integral, 'status' => SubscriptionStatus::Active], Tenant::factory()->suspended()->create());
    planFor($account, [...$integral, 'status' => SubscriptionStatus::Active], Tenant::factory()->archived()->create());
    planFor($account, [...$integral, 'status' => SubscriptionStatus::Active], Tenant::factory()->readOnly()->create());
    planFor($account, ['plan' => Plan::Cumplimiento, 'status' => SubscriptionStatus::Active]);
    planFor($other, [...$integral, 'status' => SubscriptionStatus::Active]);
    // A company paid before and no more.
    $former = planFor($account, [...$integral, 'status' => SubscriptionStatus::Active]);
    AccountCompany::query()->where('tenant_id', $former->id)->update(['ends_on' => '2026-12-31']);

    expect(resolve(Eligibility::class)->count($account))->toBe(3);
});

it('prices a company whose plan has a negotiated price in force with it, and the table when it has expired', function (): void {
    $account = BillingAccount::factory()->create();
    $valid = planFor($account, ['profile' => Profile::Direct, 'seats' => 180, 'negotiated_price_cents' => 800_000, 'negotiated_valid_until' => now()->addMonth()->toDateString()]);
    $expired = planFor($account, ['profile' => Profile::Direct, 'seats' => 180, 'negotiated_price_cents' => 800_000, 'negotiated_valid_until' => now()->subDay()->toDateString()]);
    $open = planFor($account, ['profile' => Profile::Direct, 'seats' => 180, 'negotiated_price_cents' => 700_000, 'negotiated_valid_until' => null]);

    $rows = collect($this->actingAs(superadmin())->get(route('billing.accounts.show', $account))->viewData('page')['props']['companies']['data'])->keyBy('company_id');

    expect($rows[$valid->id]['subscription']['quote']['subtotal'])->toBe('$8,000')
        ->and($rows[$valid->id]['subscription']['negotiated_price'])->toBe('8000')
        ->and($rows[$valid->id]['subscription']['negotiated_valid_until'])->toBe(now()->addMonth()->toDateString())
        ->and($rows[$expired->id]['subscription']['quote']['requires_quotation'])->toBeTrue()
        ->and($rows[$expired->id]['subscription']['quote']['subtotal'])->toBeNull()
        ->and($rows[$open->id]['subscription']['quote']['subtotal'])->toBe('$7,000');
});

it('leaves out of the totals the companies that need a quotation, and counts them', function (): void {
    $account = BillingAccount::factory()->create();
    planFor($account, ['profile' => Profile::Direct, 'seats' => 100]);
    planFor($account, ['profile' => Profile::Direct, 'seats' => 101]);
    planFor($account, ['profile' => Profile::Direct, 'seats' => 250]);

    $summary = summaryOf($account);

    expect($summary['subtotal'])->toBe('$4,900')
        ->and($summary['requiring_quotation'])->toBe(2)
        ->and($summary['with_plan'])->toBe(3);
});

it('counts the companies without a plan and prices none of them', function (): void {
    $account = BillingAccount::factory()->create();
    AccountCompany::factory()->for($account, 'account')->count(2)->create();
    planFor($account, ['profile' => Profile::Direct]);

    $summary = summaryOf($account);

    expect($summary['companies'])->toBe(3)
        ->and($summary['with_plan'])->toBe(1)
        ->and($summary['without_plan'])->toBe(2)
        ->and($summary['subtotal'])->toBe('$4,900');
});

it('has nothing to total for an account that pays no company, or no company with a plan', function (): void {
    $empty = BillingAccount::factory()->create();
    $unpriced = BillingAccount::factory()->create();
    AccountCompany::factory()->for($unpriced, 'account')->create();

    foreach ([$empty, $unpriced] as $account) {
        $summary = summaryOf($account);
        expect($summary['subtotal'])->toBeNull()->and($summary['vat'])->toBeNull()->and($summary['total'])->toBeNull()->and($summary['next_renewal'])->toBeNull();
    }
});

it('says when the next renewal is: the soonest of its companies', function (): void {
    $account = BillingAccount::factory()->create();
    planFor($account, ['profile' => Profile::Direct, 'renews_on' => '2027-03-01', 'starts_on' => '2027-02-01']);
    planFor($account, ['profile' => Profile::Direct, 'renews_on' => '2027-01-01']);
    planFor($account, ['profile' => Profile::Direct, 'renews_on' => '2027-02-01', 'starts_on' => '2027-01-01']);

    expect(summaryOf($account)['next_renewal'])->toBe('1 ene 2027');
});

it('keeps the totals of all the companies, though the list shows a page of them', function (): void {
    $account = BillingAccount::factory()->consultant()->create();
    foreach (range(1, 26) as $ignored) {
        planFor($account);
    }

    $this->actingAs(superadmin())->get(route('billing.accounts.show', $account))
        ->assertInertia(fn (Assert $page) => $page->has('companies.data', 25)->where('companies.total', 26)->where('summary.companies', 26)->where('summary.subtotal', '$103,740'));
});

it('gives the day a new plan starts unless another is chosen: today where the panel shows it', function (): void {
    $account = BillingAccount::factory()->create();
    $this->travelTo(CarbonImmutable::parse('2026-12-10 02:00:00', 'UTC'));

    // 02:00 UTC is still the 9th in Mexico City.
    $this->actingAs(superadmin())->get(route('billing.accounts.show', $account))
        ->assertInertia(fn (Assert $page) => $page->where('today', '2026-12-09'));
});

it('lets support read the same figures', function (): void {
    $account = BillingAccount::factory()->create();
    planFor($account, ['profile' => Profile::Direct]);

    $this->actingAs(support())->get(route('billing.accounts.show', $account))
        ->assertInertia(fn (Assert $page) => $page->where('summary.subtotal', '$4,900'));
});

it('asks the contract for the whole page of companies at once, and one question per company for the people it has', function (): void {
    $account = BillingAccount::factory()->consultant()->create();
    foreach (range(1, 5) as $ignored) {
        planFor($account);
    }
    $this->actingAs(superadmin());
    // The account, the page of links, its count, the companies, their centers, the plans, the payers' integral companies and the people: fixed per company, never a query per attribute.
    $queries = 0;
    Illuminate\Support\Facades\DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $this->get(route('billing.accounts.show', $account))->assertOk();

    expect($queries)->toBeLessThanOrEqual(40);
});

it('quotes with the day it is asked on', function (): void {
    $account = BillingAccount::factory()->create();
    $tenant = planFor($account, ['profile' => Profile::Direct, 'seats' => 180, 'negotiated_price_cents' => 800_000, 'negotiated_valid_until' => '2027-06-30']);
    $subscription = Subscription::query()->where('tenant_id', $tenant->id)->firstOrFail();
    $quoter = resolve(SubscriptionQuoter::class);

    expect($quoter->quote($subscription, 0, CarbonImmutable::parse('2027-06-30'))->subtotalCents)->toBe(800_000)
        ->and($quoter->quote($subscription, 0, CarbonImmutable::parse('2027-07-01'))->requiresQuotation)->toBeTrue();
});

it('writes a quote for the screen: pesos as text, lines with their explanation and no amount for a quotation', function (): void {
    $priced = QuoteView::from(App\Modules\Billing\Support\Quote::for(Plan::Cumplimiento, Profile::Direct, 100, 2, 'v1'));
    $open = QuoteView::from(App\Modules\Billing\Support\Quote::for(Plan::Cumplimiento, Profile::Direct, 101, 2, 'v1'));

    expect($priced)->toBe([
        'requires_quotation' => false,
        'explanation' => 'Subtotal más 16% de IVA',
        'lines' => [['label' => 'Cumplimiento', 'amount' => '$4,900', 'explanation' => '100 personas × $49 = $4,900']],
        'subtotal' => '$4,900',
        'subtotal_cents' => 490_000,
        'vat' => '$784',
        'total' => '$5,684',
    ])->and($open)->toBe([
        'requires_quotation' => true,
        'explanation' => 'Más de 100 personas: requiere cotización',
        'lines' => [],
        'subtotal' => null,
        'subtotal_cents' => null,
        'vat' => null,
        'total' => null,
    ]);
});
