<?php

declare(strict_types=1);

use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

it('lists who pays what, with the kind of each account and how many companies it pays', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    $consultant = BillingAccount::factory()->consultant()->create(['legal_name' => 'Consultores Asociados S.C.', 'rfc' => 'CAS010101AB1', 'contact_name' => 'Laura Pérez', 'contact_email' => 'laura@consultores.mx']);
    AccountCompany::factory()->count(2)->for($consultant, 'account')->create();
    BillingAccount::factory()->create(['legal_name' => 'Grupo Directo S.A.']);

    visitPage('billing/accounts', '/admin/billing/accounts', $dark, $mobile)
        ->assertSee('Cuentas')
        ->assertSee('2 cuentas')
        ->assertSee('Consultores Asociados S.C.')
        ->assertSee('CAS010101AB1')
        ->assertSee('Consultor')
        ->assertSee('2 empresas')
        ->assertSee('Laura Pérez')
        ->assertSee('Grupo Directo S.A.')
        ->assertSee('Crear cuenta');
})->with($looks);

it('shows an empty list with its next step', function (): void {
    signInAsSuperadmin();

    visitPage('billing/accounts', '/admin/billing/accounts')
        ->assertSee('Aún no hay cuentas')
        ->assertSee('Crea la cuenta de quien paga');
});

it('shows support the accounts without the button that creates one', function (): void {
    $this->actingAs(support());
    BillingAccount::factory()->create(['legal_name' => 'Grupo Directo S.A.']);

    visitPage('billing/accounts', '/admin/billing/accounts')
        ->assertSee('Grupo Directo S.A.')
        ->assertDontSee('Crear cuenta');
});

it('creates an account from the list and lands on its page', function (): void {
    signInAsSuperadmin();

    visitPage('billing/accounts', '/admin/billing/accounts')
        ->click('Crear cuenta')
        ->assertSee('Quien paga por una o varias empresas')
        ->click('Consultor')
        ->fill('legal_name', 'Consultores Nuevos S.C.')
        ->fill('rfc', 'CNU010101AB1')
        ->fill('contact_name', 'Marta Ruiz')
        ->fill('contact_email', 'marta@nuevos.mx')
        ->press('Guardar cuenta')
        ->assertSee('Cuenta creada.')
        ->assertSee('Consultores Nuevos S.C.');

    expect(BillingAccount::query()->sole()->kind->value)->toBe('consultant');
});

it('shows an account with its fiscal data and the way to correct it', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    $account = BillingAccount::factory()->consultant()->create(['legal_name' => 'Consultores Asociados S.C.', 'rfc' => 'CAS010101AB1', 'contact_name' => 'Laura Pérez', 'contact_email' => 'laura@consultores.mx']);

    visitPage('billing/account', "/admin/billing/accounts/{$account->id}", $dark, $mobile)
        ->assertSee('Consultores Asociados S.C.')
        ->assertSee('Consultor')
        ->assertSee('CAS010101AB1')
        ->assertSee('Editar cuenta');
})->with($looks);
