<?php

declare(strict_types=1);

use App\Http\Middleware\ForbidWhileImpersonating;
use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Billing\Actions\CreateAccount;
use App\Modules\Billing\Actions\ListAccounts;
use App\Modules\Billing\Actions\UpdateAccount;
use App\Modules\Billing\Enums\AccountKind;
use App\Modules\Billing\Http\Requests\AccountRules;
use App\Modules\Billing\Http\Requests\StoreAccountRequest;
use App\Modules\Billing\Http\Requests\UpdateAccountRequest;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use Illuminate\Support\Facades\Log;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;

mutates(CreateAccount::class, UpdateAccount::class, ListAccounts::class, AccountRules::class, StoreAccountRequest::class, UpdateAccountRequest::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function accountInput(array $overrides = []): array
{
    return [
        'kind' => 'consultant',
        'legal_name' => 'Consultores Asociados S.C.',
        'rfc' => 'CAS010101AB1',
        'tax_regime' => '601 General de Ley Personas Morales',
        'postal_code' => '06600',
        'cfdi_use' => 'G03',
        'contact_name' => 'Laura Pérez',
        'contact_email' => 'laura@consultores.mx',
        'contact_phone' => '55 1234 5678',
        'notes' => 'Paga por SPEI los primeros cinco días.',
        ...$overrides,
    ];
}

it('lives under /admin/billing/accounts', function (): void {
    expect(route('billing.accounts.index', absolute: false))->toBe('/admin/billing/accounts')
        ->and(route('billing.accounts.show', 'abc', absolute: false))->toBe('/admin/billing/accounts/abc');
});

it('sends guests to the login', function (): void {
    $this->get(route('billing.accounts.index'))->assertRedirect(route('login'));
    $this->post(route('billing.accounts.store'), accountInput())->assertRedirect(route('login'));
});

it('creates an account, logs it and audits it without personal data', function (): void {
    Log::spy();

    $response = $this->actingAs(superadmin())
        ->post(route('billing.accounts.store'), accountInput())
        ->assertSessionHasNoErrors()
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Cuenta creada.']]);

    $account = BillingAccount::query()->sole();
    $response->assertRedirect(route('billing.accounts.show', $account));

    expect($account->kind)->toBe(AccountKind::Consultant)
        ->and($account->legal_name)->toBe('Consultores Asociados S.C.')
        ->and($account->rfc)->toBe('CAS010101AB1')
        ->and($account->tax_regime)->toBe('601 General de Ley Personas Morales')
        ->and($account->postal_code)->toBe('06600')
        ->and($account->cfdi_use)->toBe('G03')
        ->and($account->contact_name)->toBe('Laura Pérez')
        ->and($account->contact_email)->toBe('laura@consultores.mx')
        ->and($account->contact_phone)->toBe('55 1234 5678')
        ->and($account->notes)->toBe('Paga por SPEI los primeros cinco días.');

    Log::shouldHaveReceived('info')->once()->with('billing.account_created', ['account_id' => $account->id, 'kind' => 'consultant']);
    $entry = AuditEntry::query()->where('event', 'billing.account_created')->sole();
    expect($entry->subject_id)->toBe($account->id)
        ->and($entry->metadata)->toBe(['kind' => 'consultant'])
        ->and(json_encode($entry->metadata))->not->toContain('Laura');
});

it('normalizes the RFC and trims the text before checking it', function (): void {
    $this->actingAs(superadmin())->post(route('billing.accounts.store'), accountInput(['rfc' => ' cas 010101 ab1 ', 'legal_name' => '  Consultores  ', 'contact_name' => ' Laura ']))
        ->assertSessionHasNoErrors();

    expect(BillingAccount::query()->sole())->rfc->toBe('CAS010101AB1')->legal_name->toBe('Consultores')->contact_name->toBe('Laura');
});

it('keeps empty optional fields as nothing', function (): void {
    $this->actingAs(superadmin())->post(route('billing.accounts.store'), accountInput(['tax_regime' => '', 'postal_code' => null, 'cfdi_use' => '', 'contact_phone' => '', 'notes' => '']))
        ->assertSessionHasNoErrors();

    expect(BillingAccount::query()->sole())->tax_regime->toBeNull()->postal_code->toBeNull()->cfdi_use->toBeNull()->contact_phone->toBeNull()->notes->toBeNull();
});

it('refuses what the database would refuse, with a message', function (array $overrides, string $field): void {
    $this->actingAs(superadmin())->post(route('billing.accounts.store'), accountInput($overrides))
        ->assertSessionHasErrors($field);

    expect(BillingAccount::query()->count())->toBe(0)->and(AuditEntry::query()->count())->toBe(0);
})->with([
    'no kind' => [['kind' => ''], 'kind'],
    'an unknown kind' => [['kind' => 'regalo'], 'kind'],
    'no legal name' => [['legal_name' => ''], 'legal_name'],
    'a legal name of two lines' => [['legal_name' => "Uno\ndos"], 'legal_name'],
    'a legal name that is too long' => [['legal_name' => str_repeat('a', 201)], 'legal_name'],
    'no RFC' => [['rfc' => ''], 'rfc'],
    'an RFC that is not one' => [['rfc' => 'no-es-rfc'], 'rfc'],
    'no contact name' => [['contact_name' => ''], 'contact_name'],
    'no contact email' => [['contact_email' => ''], 'contact_email'],
    'a contact email that is not one' => [['contact_email' => 'laura'], 'contact_email'],
    'a postal code of four digits' => [['postal_code' => '0660'], 'postal_code'],
    'a postal code of letters' => [['postal_code' => 'abcde'], 'postal_code'],
    'a CFDI use that is too long' => [['cfdi_use' => str_repeat('G', 11)], 'cfdi_use'],
    'notes that are too long' => [['notes' => str_repeat('a', 2001)], 'notes'],
]);

it('refuses an RFC another account has, and lets an account keep its own on edit', function (): void {
    $other = BillingAccount::factory()->create(['rfc' => 'CAS010101AB1']);

    $this->actingAs(superadmin())->post(route('billing.accounts.store'), accountInput())->assertSessionHasErrors('rfc');

    $this->put(route('billing.accounts.update', $other), accountInput(['contact_name' => 'Otra Persona']))->assertSessionHasNoErrors();
    expect($other->refresh()->contact_name)->toBe('Otra Persona');
});

it('edits an account but never its kind, and logs it and audits it with the fields that changed', function (): void {
    Log::spy();
    $account = BillingAccount::factory()->consultant()->create(['legal_name' => 'Antes S.C.', 'contact_name' => 'Laura']);

    $this->actingAs(superadmin())
        ->put(route('billing.accounts.update', $account), accountInput(['kind' => 'direct', 'legal_name' => 'Después S.C.', 'rfc' => $account->rfc, 'contact_name' => 'Laura']))
        ->assertRedirect(route('billing.accounts.show', $account))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Cuenta actualizada.']]);

    expect($account->refresh()->legal_name)->toBe('Después S.C.')
        ->and($account->kind)->toBe(AccountKind::Consultant);

    Log::shouldHaveReceived('info')->once()->with('billing.account_updated', ['account_id' => $account->id]);
    $entry = AuditEntry::query()->where('event', 'billing.account_updated')->sole();
    // Which fields, never their values: they are a person's name, email and phone.
    expect($entry->subject_id)->toBe($account->id)
        ->and($entry->metadata['fields'])->toContain('legal_name')
        ->and($entry->metadata['fields'])->not->toContain('contact_name')
        ->and($entry->metadata['fields'])->not->toContain('kind');
});

it('says nothing changed when nothing did, and writes no audit entry', function (): void {
    $account = BillingAccount::factory()->create();
    $same = [
        'legal_name' => $account->legal_name, 'rfc' => $account->rfc, 'contact_name' => $account->contact_name, 'contact_email' => $account->contact_email,
        'tax_regime' => '', 'postal_code' => '', 'cfdi_use' => '', 'contact_phone' => '', 'notes' => '',
    ];

    $this->actingAs(superadmin())->put(route('billing.accounts.update', $account), $same)->assertSessionHasNoErrors();

    expect(AuditEntry::query()->where('event', 'billing.account_updated')->count())->toBe(0);
});

it('lets support read the accounts and change nothing, with a 404 that reveals nothing', function (): void {
    $account = BillingAccount::factory()->create();
    $support = support();

    $this->actingAs($support)->get(route('billing.accounts.index'))->assertOk();
    $this->actingAs($support)->get(route('billing.accounts.show', $account))->assertOk();
    $this->actingAs($support)->post(route('billing.accounts.store'), accountInput())->assertNotFound();
    $this->actingAs($support)->put(route('billing.accounts.update', $account), accountInput())->assertNotFound();

    expect(BillingAccount::query()->count())->toBe(1);
});

it('answers 404 to everyone who is not staff, a company owner included', function (): void {
    $account = BillingAccount::factory()->create();
    $tenant = App\Modules\Tenancy\Models\Tenant::factory()->create();
    $owner = ownerOf($tenant);

    foreach ([User::factory()->create(), $owner] as $user) {
        $this->actingAs($user)->get(route('billing.accounts.index'))->assertNotFound();
        $this->actingAs($user)->get(route('billing.accounts.show', $account))->assertNotFound();
        $this->actingAs($user)->post(route('billing.accounts.store'), accountInput())->assertNotFound();
        $this->actingAs($user)->put(route('billing.accounts.update', $account), accountInput())->assertNotFound();
    }
});

it('answers 404 for an account that does not exist', function (): void {
    $this->actingAs(superadmin())->get(route('billing.accounts.show', '11111111-1111-4111-8111-111111111111'))->assertNotFound();
    $this->actingAs(superadmin())->get('/admin/billing/accounts/no-es-uuid')->assertNotFound();
});

it('lists the accounts in a page, newest first, with how many companies each pays now', function (): void {
    $older = BillingAccount::factory()->consultant()->create(['legal_name' => 'Antigua', 'created_at' => now()->subDay()]);
    $newer = BillingAccount::factory()->create(['legal_name' => 'Nueva']);
    AccountCompany::factory()->count(2)->for($older, 'account')->create();
    AccountCompany::factory()->for($older, 'account')->create(['ends_on' => '2026-12-31']);

    $this->actingAs(superadmin())->get(route('billing.accounts.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('billing/accounts')
            ->has('accounts.data', 2)
            ->where('accounts.data.0.id', $newer->id)
            ->where('accounts.data.0.companies', 0)
            ->where('accounts.data.1.id', $older->id)
            ->where('accounts.data.1.companies', 2)
            ->where('accounts.data.1.kind', 'consultant')
            ->where('accounts.data.1.kind_label', 'Consultor')
            ->where('accounts.data.1.legal_name', 'Antigua')
            ->hasAll(['accounts.current_page', 'accounts.last_page', 'accounts.per_page', 'accounts.total', 'accounts.links'])
            ->where('counts', ['all' => 2, 'direct' => 1, 'consultant' => 1])
            ->where('filters', ['q' => '', 'kind' => 'all']));
});

it('searches by legal name, RFC or contact, without regard to case or accents, and counts what matches', function (): void {
    BillingAccount::factory()->create(['legal_name' => 'Zapatería José S.A.', 'rfc' => 'ZAP010101AB1', 'contact_name' => 'Ana']);
    BillingAccount::factory()->consultant()->create(['legal_name' => 'Otra S.C.', 'rfc' => 'OTR010101AB1', 'contact_name' => 'José Luis']);
    BillingAccount::factory()->create(['legal_name' => 'Ninguna', 'rfc' => 'NIN010101AB1', 'contact_name' => 'Pedro']);

    $names = fn (string $query) => collect($this->actingAs(superadmin())->get(route('billing.accounts.index', $query))->viewData('page')['props']['accounts']['data'])->pluck('legal_name')->sort()->values()->all();

    expect($names('q=JOSE'))->toBe(['Otra S.C.', 'Zapatería José S.A.'])
        ->and($names('q=otr010101'))->toBe(['Otra S.C.'])
        ->and($names('q=jose&kind=consultant'))->toBe(['Otra S.C.'])
        ->and($names('q=nada'))->toBe([]);

    $this->get(route('billing.accounts.index', 'q=jose'))->assertInertia(fn (Assert $page) => $page->where('counts', ['all' => 2, 'direct' => 1, 'consultant' => 1]));
});

it('pages the accounts 25 at a time', function (): void {
    BillingAccount::factory()->count(26)->create();

    $this->actingAs(superadmin())->get(route('billing.accounts.index'))
        ->assertInertia(fn (Assert $page) => $page->has('accounts.data', 25)->where('accounts.total', 26)->where('accounts.last_page', 2));
});

it('keeps the routes that change an account out of reach while impersonating', function (): void {
    foreach (['billing.accounts.store', 'billing.accounts.update'] as $name) {
        expect(ForbidWhileImpersonating::guards($name))->toBeTrue("{$name} is not guarded");
    }
});
