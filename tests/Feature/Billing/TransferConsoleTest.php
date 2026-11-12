<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Billing\Actions\ListAccountTransfers;
use App\Modules\Billing\Actions\SearchAccounts;
use App\Modules\Billing\Http\Requests\TransferRequest;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\CompanyTransfer;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ListAccountTransfers::class, TransferRequest::class, SearchAccounts::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function transferInput(BillingAccount $to, array $overrides = []): array
{
    return ['destination' => 'account', 'to_account' => $to->id, 'effective_on' => '2027-01-01', 'reason' => 'El cliente contrata directo', ...$overrides];
}

it('lives under /admin/billing', function (): void {
    expect(route('billing.transfers.store', 'c', absolute: false))->toBe('/admin/billing/companies/c/transfer')
        ->and(route('billing.transfers.preview', 'c', absolute: false))->toBe('/admin/billing/companies/c/transfer/preview');
});

it('previews a move as JSON and writes nothing', function (): void {
    [$tenant] = paidCompany();
    $to = BillingAccount::factory()->consultant()->create();

    $this->actingAs(superadmin())->postJson(route('billing.transfers.preview', $tenant->id), transferInput($to, ['reason' => '']))
        ->assertOk()
        ->assertJsonPath('to.name', $to->legal_name)
        ->assertJsonPath('effective_on', '1 ene 2027')
        ->assertJsonPath('access', 'Trasladar una empresa no da ni quita acceso a nadie: las membresías no se tocan.');

    expect(CompanyTransfer::query()->count())->toBe(0);
});

it('moves the company and goes to the account that receives it', function (): void {
    [$tenant, $from] = paidCompany();
    $to = BillingAccount::factory()->create();

    $this->actingAs(superadmin())->post(route('billing.transfers.store', $tenant->id), transferInput($to))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('billing.accounts.show', $to))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Empresa trasladada.']]);

    expect(AccountCompany::query()->current()->where('tenant_id', $tenant->id)->sole()->billing_account_id)->toBe($to->id);

    [$other, $payer] = paidCompany();
    $this->post(route('billing.transfers.store', $other->id), transferInput($to, ['destination' => 'none', 'to_account' => '']))
        ->assertRedirect(route('billing.accounts.show', $payer));
});

it('shows the errors of the rules and of the form on their fields', function (array $overrides, string $field): void {
    [$tenant] = paidCompany();
    $input = transferInput(BillingAccount::factory()->create(), $overrides);

    $this->actingAs(superadmin())->post(route('billing.transfers.store', $tenant->id), $input)->assertSessionHasErrors($field);

    expect(CompanyTransfer::query()->count())->toBe(0);
})->with([
    'no reason' => [['reason' => ''], 'reason'],
    'a reason that is too long' => [['reason' => str_repeat('a', 501)], 'reason'],
    'no date' => [['effective_on' => ''], 'effective_on'],
    'a date that is not one' => [['effective_on' => 'pronto'], 'effective_on'],
    'a day that is not a cut' => [['effective_on' => '2027-01-02'], 'effective_on'],
    'an account that is not an id' => [['to_account' => 'otra'], 'to_account'],
    'no account chosen' => [['to_account' => ''], 'to_account'],
    'no destination' => [['destination' => ''], 'destination'],
    'a destination that is not one' => [['destination' => 'luna'], 'destination'],
    'a profile that does not exist' => [['profile' => 'gratis'], 'profile'],
]);

it('is the superadmin\'s alone: support and owners get a 404 and guests the login', function (): void {
    [$tenant] = paidCompany();
    $input = transferInput(BillingAccount::factory()->create());

    foreach ([support(), ownerOf($tenant)] as $user) {
        $this->actingAs($user)->post(route('billing.transfers.store', $tenant->id), $input)->assertNotFound();
        $this->postJson(route('billing.transfers.preview', $tenant->id), $input)->assertNotFound();
    }

    auth()->logout();
    $this->post(route('billing.transfers.store', $tenant->id), $input)->assertRedirect(route('login'));
    $this->actingAs(superadmin())->post(route('billing.transfers.store', '11111111-1111-4111-8111-111111111111'), $input)->assertNotFound();

    expect(CompanyTransfer::query()->count())->toBe(0);
});

it('keeps the transfer routes out of reach while impersonating', function (): void {
    foreach (['billing.transfers.store', 'billing.transfers.preview'] as $name) {
        expect(App\Http\Middleware\ForbidWhileImpersonating::guards($name))->toBeTrue();
    }
});

it('lists the moves on both accounts, a page at a time, and says where the company came from', function (): void {
    [$tenant, $from] = paidCompany();
    $to = BillingAccount::factory()->consultant()->create();
    $admin = User::factory()->create(['name' => 'Victor Bravo']);
    CompanyTransfer::factory()->create(['tenant_id' => $tenant->id, 'from_account_id' => $from->id, 'to_account_id' => $to->id, 'effective_on' => '2027-01-01', 'reason' => 'Cambio de modelo', 'created_by' => $admin->id, 'profile_before' => 'direct', 'profile_after' => 'channel_compliance']);
    AccountCompany::query()->where('billing_account_id', $from->id)->update(['ends_on' => '2027-01-01']);
    AccountCompany::factory()->for($to, 'account')->create(['tenant_id' => $tenant->id, 'starts_on' => '2027-01-01']);
    CompanyTransfer::factory()->create(['to_account_id' => null, 'from_account_id' => $to->id]);

    $this->actingAs(support())->get(route('billing.accounts.show', $from))->assertInertia(fn (Assert $page) => $page
        ->has('transfers.data', 1)
        ->where('transfers.total', 1)
        ->hasAll(['transfers.current_page', 'transfers.last_page', 'transfers.links'])
        ->where('transfers.data.0.company', $tenant->trade_name)
        ->where('transfers.data.0.direction', 'out')
        ->where('transfers.data.0.other', $to->legal_name)
        ->where('transfers.data.0.effective_on', '1 ene 2027')
        ->where('transfers.data.0.reason', 'Cambio de modelo')
        ->where('transfers.data.0.by', 'Victor Bravo')
        ->where('transfers.data.0.profile', 'Directo a Canal Cumplimiento'));

    $this->get(route('billing.accounts.show', $to))->assertInertia(fn (Assert $page) => $page
        ->has('transfers.data', 2)
        ->where('transfers.data.0.direction', 'out')
        ->where('transfers.data.0.other', 'Sin pagador')
        ->where('transfers.data.1.direction', 'in')
        ->where('transfers.data.1.other', $from->legal_name)
        ->where('companies.data.0.previous_payer', $from->legal_name));
});

it('pages the moves 25 at a time', function (): void {
    [, $from] = paidCompany();
    CompanyTransfer::factory()->count(26)->create(['from_account_id' => $from->id]);

    $this->actingAs(superadmin())->get(route('billing.accounts.show', $from))
        ->assertInertia(fn (Assert $page) => $page->has('transfers.data', 25)->where('transfers.total', 26));
});

it('searches the accounts on the server for the destination field, by name or RFC', function (): void {
    $match = BillingAccount::factory()->consultant()->create(['legal_name' => 'Asesores Ñandú SA', 'rfc' => 'AND010101AB1']);
    BillingAccount::factory()->create(['legal_name' => 'Otra Cosa SA']);
    BillingAccount::factory()->count(25)->create(['legal_name' => 'Asesores Masivos']);

    $found = $this->actingAs(superadmin())->getJson(route('billing.accounts.search', ['q' => 'nandu']))->assertOk()->json();
    expect($found)->toBe([['value' => $match->id, 'label' => 'Asesores Ñandú SA · AND010101AB1 · Consultor']]);

    $this->getJson(route('billing.accounts.search', ['q' => 'asesores']))->assertOk()->assertJsonCount(20);
    $this->actingAs(support())->getJson(route('billing.accounts.search', ['q' => 'a']))->assertNotFound();
});
