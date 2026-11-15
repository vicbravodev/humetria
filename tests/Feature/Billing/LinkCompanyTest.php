<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Billing\Actions\LinkCompany;
use App\Modules\Billing\Http\Requests\LinkCompanyRequest;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\Log;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;

mutates(LinkCompany::class, LinkCompanyRequest::class);

it('links an existing company to an account from a day, and logs and audits it', function (): void {
    Log::spy();
    $account = BillingAccount::factory()->consultant()->create();
    $tenant = Tenant::factory()->create();

    $this->actingAs(superadmin())
        ->post(route('billing.accounts.companies.store', $account), ['company_id' => $tenant->id, 'starts_on' => '2026-12-01'])
        ->assertRedirect(route('billing.accounts.show', $account))
        ->assertSessionHasNoErrors()
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Empresa vinculada.']]);

    $link = AccountCompany::query()->sole();

    expect($link->billing_account_id)->toBe($account->id)
        ->and($link->tenant_id)->toBe($tenant->id)
        ->and($link->starts_on->toDateString())->toBe('2026-12-01')
        ->and($link->ends_on)->toBeNull();

    Log::shouldHaveReceived('info')->once()->with('billing.company_linked', ['account_id' => $account->id, 'tenant_id' => $tenant->id]);
    $entry = AuditEntry::query()->where('event', 'billing.company_linked')->sole();
    expect($entry->subject_id)->toBe($link->id)->and($entry->metadata)->toEqualCanonicalizing(['account_id' => $account->id, 'tenant_id' => $tenant->id, 'starts_on' => '2026-12-01']);
});

it('starts today when no day is given', function (): void {
    $account = BillingAccount::factory()->create();
    $tenant = Tenant::factory()->create();

    $this->actingAs(superadmin())->post(route('billing.accounts.companies.store', $account), ['company_id' => $tenant->id])->assertSessionHasNoErrors();

    expect(AccountCompany::query()->sole()->starts_on->toDateString())->toBe(now(config()->string('app.display_timezone'))->toDateString());
});

it('refuses a company that already has a payer, naming it, and one that does not exist', function (): void {
    $payer = BillingAccount::factory()->create(['legal_name' => 'Pagador Actual S.A.']);
    $other = BillingAccount::factory()->consultant()->create();
    $tenant = Tenant::factory()->create();
    AccountCompany::factory()->for($payer, 'account')->create(['tenant_id' => $tenant->id]);

    $this->actingAs(superadmin())
        ->post(route('billing.accounts.companies.store', $other), ['company_id' => $tenant->id])
        ->assertSessionHasErrors(['company_id' => 'Esta empresa ya la paga Pagador Actual S.A.']);
    $this->post(route('billing.accounts.companies.store', $payer), ['company_id' => $tenant->id])
        ->assertSessionHasErrors(['company_id' => 'Esta empresa ya la paga Pagador Actual S.A.']);
    $this->post(route('billing.accounts.companies.store', $other), ['company_id' => '11111111-1111-4111-8111-111111111111'])
        ->assertSessionHasErrors('company_id');
    $this->post(route('billing.accounts.companies.store', $other), ['company_id' => 'no-es-uuid'])
        ->assertSessionHasErrors('company_id');
    $this->post(route('billing.accounts.companies.store', $other), [])
        ->assertSessionHasErrors('company_id');
    $this->post(route('billing.accounts.companies.store', $other), ['company_id' => Tenant::factory()->create()->id, 'starts_on' => 'ayer'])
        ->assertSessionHasErrors('starts_on');

    expect(AccountCompany::query()->count())->toBe(1)
        ->and(AuditEntry::query()->where('event', 'billing.company_linked')->count())->toBe(0);
});

it('lets a company with a closed link be linked again, and keeps the history', function (): void {
    $tenant = Tenant::factory()->create();
    $first = BillingAccount::factory()->create();
    $second = BillingAccount::factory()->consultant()->create();
    AccountCompany::factory()->for($first, 'account')->create(['tenant_id' => $tenant->id, 'starts_on' => '2026-01-01', 'ends_on' => '2026-06-30']);

    $this->actingAs(superadmin())
        ->post(route('billing.accounts.companies.store', $second), ['company_id' => $tenant->id, 'starts_on' => '2026-07-01'])
        ->assertSessionHasNoErrors();

    expect(AccountCompany::query()->where('tenant_id', $tenant->id)->count())->toBe(2);
});

it('is the superadmin\'s alone: support and everyone else get a 404 and nothing is linked', function (): void {
    $account = BillingAccount::factory()->create();
    $tenant = Tenant::factory()->create();

    $this->actingAs(support())->post(route('billing.accounts.companies.store', $account), ['company_id' => $tenant->id])->assertNotFound();
    $this->actingAs(ownerOf($tenant))->post(route('billing.accounts.companies.store', $account), ['company_id' => $tenant->id])->assertNotFound();

    expect(AccountCompany::query()->count())->toBe(0);
});

it('lists the companies of the account, one page at a time, with their name, status and centers, and no plan yet', function (): void {
    $account = BillingAccount::factory()->consultant()->create();
    $other = BillingAccount::factory()->create();
    $acme = Tenant::factory()->create(['trade_name' => 'Acme', 'rfc' => 'ACM010101AB1']);
    $beta = Tenant::factory()->suspended()->create(['trade_name' => 'Beta']);
    $foreign = Tenant::factory()->create(['trade_name' => 'Ajena']);
    $past = Tenant::factory()->create(['trade_name' => 'Pasada']);
    WorkCenter::factory()->for($acme)->count(2)->create();
    AccountCompany::factory()->for($account, 'account')->create(['tenant_id' => $acme->id, 'starts_on' => '2026-12-01']);
    AccountCompany::factory()->for($account, 'account')->create(['tenant_id' => $beta->id, 'starts_on' => '2026-11-01']);
    AccountCompany::factory()->for($other, 'account')->create(['tenant_id' => $foreign->id]);
    AccountCompany::factory()->for($account, 'account')->create(['tenant_id' => $past->id, 'ends_on' => '2026-12-31']);

    $this->actingAs(superadmin())->get(route('billing.accounts.show', $account))
        ->assertInertia(fn (Assert $page) => $page
            ->component('billing/account')
            ->has('companies.data', 2)
            ->where('companies.total', 2)
            ->hasAll(['companies.current_page', 'companies.last_page', 'companies.per_page', 'companies.links'])
            ->where('companies.data.0.trade_name', 'Acme')
            ->where('companies.data.0.rfc', 'ACM010101AB1')
            ->where('companies.data.0.status_label', 'Sin iniciar')
            ->where('companies.data.0.work_centers', 2)
            ->where('companies.data.0.linked_since', '1 dic 2026')
            ->where('companies.data.0.subscription', null)
            ->where('companies.data.1.trade_name', 'Beta')
            ->where('companies.data.1.status', 'suspended'));
});

it('does not give away other companies of other accounts, and sends no personal data of the companies', function (): void {
    $account = BillingAccount::factory()->create();
    $tenant = Tenant::factory()->create();
    AccountCompany::factory()->for($account, 'account')->create(['tenant_id' => $tenant->id]);

    $this->actingAs(support())->get(route('billing.accounts.show', $account))
        ->assertInertia(fn (Assert $page) => $page->has('companies.data.0', fn (Assert $row) => $row->hasAll(['company_id', 'trade_name', 'legal_name', 'rfc', 'slug', 'status', 'status_label', 'work_centers', 'linked_since', 'subscription'])->etc()));
});

it('finds the companies that have no payer yet, by name or RFC, for the field that links them', function (): void {
    $account = BillingAccount::factory()->create();
    $free = Tenant::factory()->create(['trade_name' => 'Zapatería Libre', 'rfc' => 'ZAP010101AB1']);
    $taken = Tenant::factory()->create(['trade_name' => 'Zapatería Ocupada']);
    $closed = Tenant::factory()->create(['trade_name' => 'Zapatería Cerrada']);
    AccountCompany::factory()->for($account, 'account')->create(['tenant_id' => $taken->id]);
    AccountCompany::factory()->for($account, 'account')->create(['tenant_id' => $closed->id, 'ends_on' => '2026-12-31']);

    $found = $this->actingAs(superadmin())->getJson(route('billing.companies.search', ['q' => 'zapateria']))->assertOk()->json();

    expect($found)->toBe([
        ['value' => $closed->id, 'label' => 'Zapatería Cerrada · '.$closed->rfc],
        ['value' => $free->id, 'label' => 'Zapatería Libre · ZAP010101AB1'],
    ]);
});

it('keeps the search of companies for the superadmin', function (): void {
    $this->actingAs(support())->getJson(route('billing.companies.search', ['q' => 'a']))->assertNotFound();
    $this->actingAs(ownerOf(Tenant::factory()->create()))->getJson(route('billing.companies.search', ['q' => 'a']))->assertNotFound();
});
